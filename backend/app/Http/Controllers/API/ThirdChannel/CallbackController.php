<?php

namespace App\Http\Controllers\API\ThirdChannel;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCallbackJob;
use App\Models\CallbackLog;
use App\Models\ProductOrder;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ChannelConfigLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    /**
     * 接收下游系统的推送回调通知
     *
     * @param  Request  $request
     * @param  string  $pid  渠道 PID
     * @return JsonResponse
     */
    public function callback(Request $request, string $pid): JsonResponse
    {
        // 1. 查找渠道
        $channel = ThirdChannels::where('pid', $pid)->first();
        if (! $channel) {
            return $this->error('渠道不存在', 404);
        }

        // 2. 验证回调签名（共用 channel.auth 中间件）
        // 已由中间件验证，此处获取已验证的渠道信息
        $authenticatedChannel = $request->get('_authenticated_channel');
        if (! $authenticatedChannel || $authenticatedChannel->pid !== $pid) {
            return $this->error('回调签名验证失败', 403);
        }

        // 3. 获取回调参数
        $orderNo = $request->input('order_no');
        $resultStatus = $request->input('result_status'); // success / failed
        $resultMsg = $request->input('result_message', '');

        if (! $orderNo || ! $resultStatus) {
            return $this->error('缺少必要参数: order_no, result_status', 422);
        }

        if (! in_array($resultStatus, ['success', 'failed'])) {
            return $this->error('无效的 result_status，仅支持 success/failed', 422);
        }

        // 4. 更新订单状态
        $order = ProductOrder::where('order_no', $orderNo)
            ->where('pid', $pid)
            ->first();

        if (! $order) {
            return $this->error('订单不存在', 404);
        }

        $order->update([
            'sync_status' => $resultStatus === 'success' ? 1 : 2,
            'sync_error' => $resultStatus === 'failed' ? $resultMsg : null,
            'pushed_at' => $resultStatus === 'success' ? now() : $order->pushed_at,
        ]);

        // 5. 记录回调日志
        Log::channel('push')->info('推送回调接收', [
            'pid' => $pid,
            'order_no' => $orderNo,
            'result' => $resultStatus,
            'message' => $resultMsg,
        ]);

        // 6. 如果渠道配置了回调通知地址，将结果转发给来源系统
        if ($channel->callback_url) {
            $this->notifyOriginSystem($channel, $order, $resultStatus, $resultMsg);
        }

        return $this->success(null, '回调处理成功');
    }

    /**
     * 通用回调接收端点（配置驱动）
     * 接收下游系统的回调推送，存储原始数据后异步处理
     *
     * GET|POST /api/v2/third-channel/callback-receive/{callback_type}
     *
     * @param  Request  $request
     * @param  string  $callbackType  回调类型，如 province_order_result
     * @return \Illuminate\Http\Response
     */
    public function receive(Request $request, string $callbackType)
    {
        // 1. 根据 callback_type 查找匹配的渠道
        $channel = ThirdChannels::where('callback_config->type', $callbackType)
            ->where('callback_config->enabled', true)
            ->first();

        if (! $channel) {
            Log::warning('回调接收失败：未找到匹配的渠道', [
                'callback_type' => $callbackType,
                'ip' => $request->ip(),
            ]);
            return response('渠道未配置', 404);
        }

        // 2. 提取请求参数
        $params = $request->method() === 'GET'
            ? $request->query()
            : $request->post();

        // 3. 存储原始数据到 callback_logs
        $callbackLog = CallbackLog::create([
            'channel_pid' => $channel->pid,
            'callback_type' => $callbackType,
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_params' => $params,
            'request_headers' => $request->header(),
            'raw_body' => $request->getContent() ?: null,
            'status' => CallbackLog::STATUS_PENDING,
        ]);

        Log::info('回调接收成功，已存储原始数据', [
            'callback_log_id' => $callbackLog->id,
            'callback_type' => $callbackType,
            'channel_pid' => $channel->pid,
        ]);

        // 4. 投递异步处理 Job
        ProcessCallbackJob::dispatch($callbackLog->id);

        // 5. 返回纯文本响应
        return response('success', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * 推送完整订单数据到来源系统（A 公司）
     * 当 C 回调 B 告知处理结果后，B 将完整订单数据推送给 A
     * 异步执行，不阻塞回调响应
     */
    protected function notifyOriginSystem($channel, $order, string $resultStatus, string $resultMsg): void
    {
        $callbackUrl = $channel->callback_url;
        if (! $callbackUrl) {
            return;
        }

        $timeout = $channel->push_timeout ?? 10;

        // 准备推送数据（与批量推送的数据格式一致）
        $pushData = $order->toArray();
        $internalFields = ['id', 'sync_status', 'pushed_at', 'sync_error',
            'link_id', 'trace_id', 'product_id', 'business_id', 'channel_id',
            'organization_id', 'deleted_at', 'created_at', 'updated_at',
            'ext_json', 'internal_amount', 'settlement_id', 'settle_status'];
        foreach ($internalFields as $field) {
            unset($pushData[$field]);
        }

        // 追加回调处理结果
        $pushData['callback_result'] = $resultStatus;
        $pushData['callback_message'] = $resultMsg;
        $pushData['callback_processed_at'] = now()->toDateTimeString();

        // 直接执行推送（不依赖队列驱动，避免 database 队列不执行 afterResponse 的问题）
        try {
            $response = \Illuminate\Support\Facades\Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($callbackUrl, $pushData);

            $result = $response->json() ?? [];
            $httpStatus = $response->status();

            // 评估推送结果
            $isSuccess = $this->evaluatePushResult($result, $channel);
            $isFail = $this->evaluatePushFail($result, $channel);

            if ($isSuccess || (!$isFail && $httpStatus >= 200 && $httpStatus < 300)) {
                Log::channel('push')->info('已推送完整订单数据到来源系统', [
                    'pid' => $channel->pid,
                    'order_no' => $pushData['order_no'] ?? 'N/A',
                    'callback_url' => $callbackUrl,
                    'http_status' => $httpStatus,
                ]);
            } else {
                $errorMsg = $result['msg'] ?? $result['message'] ?? "HTTP {$httpStatus}";
                Log::channel('push_failure')->warning('推送完整订单数据到来源系统失败', [
                    'pid' => $channel->pid,
                    'order_no' => $pushData['order_no'] ?? 'N/A',
                    'callback_url' => $callbackUrl,
                    'error' => $errorMsg,
                    'http_status' => $httpStatus,
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('push_failure')->warning('推送完整订单数据到来源系统异常', [
                'pid' => $channel->pid,
                'order_no' => $pushData['order_no'] ?? 'N/A',
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 根据渠道配置的成功规则评估推送结果
     */
    protected function evaluatePushResult(array $result, $channel): bool
    {
        $successRule = $channel->push_success_rule ?? null;
        if (! $successRule || ! isset($successRule['field'])) {
            return false;
        }

        $fieldValue = data_get($result, $successRule['field']);
        $expectedValue = $successRule['value'] ?? null;
        $operator = $successRule['operator'] ?? 'eq';

        return $this->compareValue($fieldValue, $expectedValue, $operator);
    }

    /**
     * 根据渠道配置的失败规则评估推送结果
     */
    protected function evaluatePushFail(array $result, $channel): bool
    {
        $failRule = $channel->push_fail_rule ?? null;
        if (! $failRule || ! isset($failRule['field'])) {
            return false;
        }

        $fieldValue = data_get($result, $failRule['field']);
        $expectedValue = $failRule['value'] ?? null;
        $operator = $failRule['operator'] ?? 'eq';

        return $this->compareValue($fieldValue, $expectedValue, $operator);
    }

    /**
     * 值比较（与 BaseProductOrderController 中的逻辑一致）
     */
    protected function compareValue(mixed $fieldValue, mixed $expectedValue, string $operator): bool
    {
        return match ($operator) {
            'eq', '==' => $fieldValue == $expectedValue,
            '===' => $fieldValue === $expectedValue,
            'neq', '!=' => $fieldValue != $expectedValue,
            'gt' => $fieldValue > $expectedValue,
            'gte', '>=' => $fieldValue >= $expectedValue,
            'lt' => $fieldValue < $expectedValue,
            'lte', '<=' => $fieldValue <= $expectedValue,
            'in' => is_array($expectedValue) ? in_array($fieldValue, $expectedValue) : false,
            'not_in' => is_array($expectedValue) ? ! in_array($fieldValue, $expectedValue) : false,
            'contains' => is_string($fieldValue) && str_contains($fieldValue, (string) $expectedValue),
            'regex' => is_string($fieldValue) && preg_match((string) $expectedValue, $fieldValue) === 1,
            default => $fieldValue == $expectedValue,
        };
    }
}