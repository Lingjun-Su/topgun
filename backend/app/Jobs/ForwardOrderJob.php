<?php

namespace App\Jobs;

use App\Models\ForwardOrder;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ChannelConfigLoader;
use App\Services\ThirdChannel\DataMapper;
use App\Services\ThirdChannel\SignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 数据中转处理 Job
 * 支持多步骤推送（如：getCode → submit），支持步骤间数据传递
 * 接收A公司数据，按步骤转换后推送到移动，记录完整中转过程
 */
class ForwardOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务最大重试次数
     */
    public $tries = 3;

    /**
     * 重试间隔（秒）
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function __construct(
        protected int $forwardOrderId,
        protected int $stepIndex = 0
    ) {}

    /**
     * 执行数据中转
     */
    public function handle(
        ChannelConfigLoader $configLoader,
        DataMapper $dataMapper,
        SignService $signService
    ): void {
        // 1. 加载中转记录
        $forwardOrder = ForwardOrder::find($this->forwardOrderId);
        if (! $forwardOrder) {
            Log::warning('中转任务跳过：记录不存在', ['forward_order_id' => $this->forwardOrderId]);
            return;
        }

        // 2. 加载目标渠道
        $targetChannel = $configLoader->load($forwardOrder->target_pid);
        if (! $targetChannel) {
            $this->markFailed($forwardOrder, '目标渠道不存在: '.$forwardOrder->target_pid);
            return;
        }

        // 3. 获取步骤配置
        $steps = $targetChannel->push_steps ?? [];
        $isMultiStep = ! empty($steps) && is_array($steps);

        // 3a. 获取当前步骤的 URL 和 request_mapping
        if ($isMultiStep && isset($steps[$this->stepIndex])) {
            $step = $steps[$this->stepIndex];
            $pushUrl = $configLoader->getStepUrl($targetChannel, $step);
            $requestMapping = $step['request_mapping'] ?? null;
            $stepName = $step['name'] ?? "step_{$this->stepIndex}";
        } else {
            // 兼容单步骤模式（没有 push_steps 配置或不匹配）
            $step = null;
            $pushUrl = $configLoader->getPushUrl($targetChannel);
            $requestMapping = null;
            $stepName = 'single';
        }

        if (! $pushUrl) {
            $this->markFailed($forwardOrder, '目标渠道未配置推送地址');
            return;
        }

        // 4. 合并数据：source_data（A公司原始数据）+ step_data（之前步骤的输出，如 linkId）
        $sourceData = $forwardOrder->source_data ?? [];
        $stepData = $forwardOrder->step_data ?? [];
        $mergedData = array_merge($sourceData, $stepData);

        // 5. 应用请求映射
        if ($requestMapping) {
            $transformedData = $dataMapper->mapForPush($mergedData, $requestMapping);
        } else {
            $transformedData = $mergedData;
        }

        // 注入追踪标识
        $transformedData['_forward_id'] = $forwardOrder->id;
        $transformedData['_source_order_no'] = $forwardOrder->source_order_no;

        Log::info('数据中转映射完成', [
            'forward_order_id' => $forwardOrder->id,
            'step_index' => $this->stepIndex,
            'step_name' => $stepName,
            'source_pid' => $forwardOrder->source_pid,
            'target_pid' => $forwardOrder->target_pid,
            'has_request_mapping' => $requestMapping ? 'yes' : 'no',
            'fields_count' => count($transformedData),
        ]);

        // 保存转换后的数据
        $forwardOrder->update(['transformed_data' => $transformedData]);

        // 6. 生成签名（如果渠道配置了签名）
        $signature = null;
        $signAlgorithm = 'sha256';
        $signConfig = $signService->getConfigForChannel($targetChannel);
        if ($signConfig['key'] ?? null) {
            $signature = $signService->sign($transformedData, $signConfig['key'], $signConfig['algorithm']);
            $signAlgorithm = $signConfig['algorithm'];
        }

        // 7. 发送 HTTP 请求
        try {
            $timeout = $configLoader->getPushTimeout($targetChannel);
            $http = Http::timeout($timeout)
                ->withHeaders([
                    'X-Forward-Id' => (string) $forwardOrder->id,
                    'Content-Type' => 'application/json',
                ]);

            if ($signature) {
                $http = $http->withHeaders([
                    'X-Sign' => $signature,
                    'X-Sign-Algorithm' => $signAlgorithm,
                ]);
            }

            $response = $http->post($pushUrl, $transformedData);
            $result = $response->json();

            // 8. 判定结果
            $isSuccess = $this->evaluateResponseSuccess($result, $step, $targetChannel);

            if ($response->successful() && $isSuccess) {
                // 转发成功 → 处理结果
                $this->handleStepSuccess($forwardOrder, $result, $step, $targetChannel);
            } else {
                // 业务失败
                $errorMsg = $result['msg'] ?? $result['message'] ?? $result['errorCode'] ?? '目标系统返回错误';
                $this->markFailed($forwardOrder, "{$stepName} 失败: {$errorMsg}");

                Log::error('数据中转业务失败', [
                    'forward_order_id' => $forwardOrder->id,
                    'step_index' => $this->stepIndex,
                    'step_name' => $stepName,
                    'response' => $result,
                ]);

                throw new \Exception("中转业务失败 ({$stepName}): {$errorMsg}");
            }
        } catch (\Exception $e) {
            // 网络异常或超时
            $this->markFailed($forwardOrder, $e->getMessage());

            Log::error('数据中转网络异常', [
                'forward_order_id' => $forwardOrder->id,
                'step_index' => $this->stepIndex,
                'step_name' => $stepName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 处理步骤成功：提取数据、更新状态、记录响应
     */
    protected function handleStepSuccess(
        ForwardOrder $forwardOrder,
        array $result,
        ?array $step,
        ThirdChannels $targetChannel
    ): void {
        $steps = $targetChannel->push_steps ?? [];
        $isLastStep = ! $step || ($this->stepIndex >= count($steps) - 1);

        // 提取下一步需要的数据（如 linkId）
        $newStepData = $forwardOrder->step_data ?? [];
        if ($step && isset($step['output_mapping'])) {
            foreach ($step['output_mapping'] as $targetKey => $sourcePath) {
                $extracted = data_get($result, $sourcePath);
                if ($extracted !== null) {
                    $newStepData[$targetKey] = $extracted;
                }
            }
        } else {
            // 默认提取逻辑：getCode 步骤的响应中提取 linkId
            $linkId = $result['data']['linkId'] ?? $result['linkId'] ?? null;
            if ($linkId) {
                $newStepData['linkId'] = $linkId;
            }
        }

        // 记录步骤响应
        $stepResponses = $forwardOrder->step_responses ?? [];
        $stepResponses[] = [
            'step_index' => $this->stepIndex,
            'step_name' => $step['name'] ?? "step_{$this->stepIndex}",
            'request' => $forwardOrder->transformed_data ?? [],
            'response' => $result,
            'time' => now()->toDateTimeString(),
        ];

        if ($isLastStep) {
            // 最后一步 → 完成
            $forwardOrder->update([
                'forward_status' => ForwardOrder::STATUS_SUCCESS,
                'target_response' => $result,
                'target_order_no' => $result['data']['order_no'] ?? $result['order_no'] ?? $result['data']['transId'] ?? null,
                'step_data' => $newStepData,
                'step_responses' => $stepResponses,
                'current_step' => $this->stepIndex + 1,
            ]);

            Log::info('数据中转完成（所有步骤成功）', [
                'forward_order_id' => $forwardOrder->id,
                'source_order_no' => $forwardOrder->source_order_no,
                'target_pid' => $forwardOrder->target_pid,
                'steps_completed' => $this->stepIndex + 1,
            ]);

            // 触发结果回写
            $this->dispatchCallback($forwardOrder);
        } else {
            // 中间步骤 → 更新状态为待验证，等待下一步触发
            $forwardOrder->update([
                'forward_status' => ForwardOrder::STATUS_VERIFYING,
                'target_response' => $result,
                'step_data' => $newStepData,
                'step_responses' => $stepResponses,
                'current_step' => $this->stepIndex + 1,
            ]);

            Log::info('数据中转步骤完成，等待下一步', [
                'forward_order_id' => $forwardOrder->id,
                'step_index' => $this->stepIndex,
                'next_step_index' => $this->stepIndex + 1,
                'source_order_no' => $forwardOrder->source_order_no,
            ]);
        }
    }

    /**
     * 评估响应是否为成功
     * 优先级：步骤级 success_rule → 渠道级 push_success_rule → 默认规则
     *
     * 逻辑说明：
     * - 如果步骤级配置了 success_rule，以它为准（匹配→成功，不匹配→失败）
     * - 如果步骤级未配置，则检查渠道级 push_success_rule
     * - 如果都未配置，使用默认规则（data.code == "00000"）
     */
    protected function evaluateResponseSuccess(array $result, ?array $step, ThirdChannels $channel): bool
    {
        // 1. 步骤级规则（优先级最高）
        if ($step && isset($step['success_rule'])) {
            $rule = $step['success_rule'];
            if (isset($rule['field'])) {
                $fieldValue = data_get($result, $rule['field']);
                $expectedValue = $rule['value'] ?? null;
                $operator = $rule['operator'] ?? 'eq';
                if ($this->compareValue($fieldValue, $expectedValue, $operator)) {
                    return true;
                }
            }
            // 步骤级规则已配置但未匹配 → 直接判定失败，不继续检查渠道级规则
            return false;
        }

        // 2. 渠道级规则（步骤级未配置时使用）
        $channelRule = $channel->push_success_rule ?? null;
        if ($channelRule && isset($channelRule['field'])) {
            $fieldValue = data_get($result, $channelRule['field']);
            $expectedValue = $channelRule['value'] ?? null;
            $operator = $channelRule['operator'] ?? 'eq';
            if ($this->compareValue($fieldValue, $expectedValue, $operator)) {
                return true;
            }
            // 渠道级规则已配置但未匹配 → 直接判定失败
            return false;
        }

        // 3. 默认规则：检查 data.code == "00000"（移动标准成功响应格式）
        $dataCode = data_get($result, 'data.code');
        return $dataCode === '00000';
    }

    /**
     * 值比较
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

    /**
     * 标记中转失败（使用原子递增避免并发问题）
     */
    protected function markFailed(ForwardOrder $forwardOrder, string $error): void
    {
        $forwardOrder->update([
            'forward_status' => ForwardOrder::STATUS_FAILED,
            'forward_error' => $error,
        ]);

        // 原子递增 retry_count，避免并发写入时读取到过期值
        DB::table('forward_orders')
            ->where('id', $forwardOrder->id)
            ->increment('retry_count');
    }

    /**
     * 推送成功后触发回调通知
     */
    protected function dispatchCallback(ForwardOrder $forwardOrder): void
    {
        if (! $forwardOrder->callback_url) {
            return;
        }

        // 已回调过的不再重复
        if ($forwardOrder->callback_status !== ForwardOrder::CALLBACK_PENDING) {
            return;
        }

        NotifyOriginJob::dispatch($forwardOrder->id);
    }

    /**
     * 最终失败处理
     */
    public function failed(\Throwable $exception): void
    {
        $forwardOrder = ForwardOrder::find($this->forwardOrderId);
        if ($forwardOrder) {
            $forwardOrder->update([
                'forward_status' => ForwardOrder::STATUS_FAILED,
                'forward_error' => $exception->getMessage(),
            ]);
        }

        Log::critical('数据中转彻底失败，已停止重试', [
            'forward_order_id' => $this->forwardOrderId,
            'final_error' => $exception->getMessage(),
        ]);
    }
}