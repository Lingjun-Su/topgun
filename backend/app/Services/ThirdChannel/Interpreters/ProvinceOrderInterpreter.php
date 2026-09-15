<?php

namespace App\Services\ThirdChannel\Interpreters;

use App\Events\OrderStatusUpdated;
use App\Models\CallbackLog;
use App\Models\ForwardOrder;
use App\Models\ProductOrder;
use App\Services\ThirdChannel\CallbackInterpreter;
use Illuminate\Support\Facades\Log;

class ProvinceOrderInterpreter implements CallbackInterpreter
{
    /**
     * 省份运营商订单结果回调解释器
     *
     * 外部参数示例（通过 field_mapping 映射后）：
     *   link_id     ← transId   : 验证码链接ID，用于匹配 ForwardOrder
     *   mobile      ← mobile    : 手机号
     *   result_code ← resCode   : 00000=成功
     *   result_msg  ← resMsg    : 结果消息
     *   order_status← orderStatus: 0=成功, 1=失败
     *   error_code  ← errorCode : 错误码
     *   amount      ← fee       : 金额
     *   order_time  ← orderTime : 订单时间
     *   province    ← province  : 省份
     */
    public function interpret(CallbackLog $log, array $config): array
    {
        $params = $log->request_params ?? [];
        $fieldMapping = $config['field_mapping'] ?? [];
        $successRule = $config['success_rule'] ?? null;
        $orderStatusMap = $config['order_status_map'] ?? [];

        // 1. 根据 field_mapping 提取内部字段
        $mapped = $this->applyFieldMapping($params, $fieldMapping);

        $linkId = $mapped['link_id'] ?? null;
        $mobile = $mapped['mobile'] ?? null;
        $resultCode = $mapped['result_code'] ?? null;
        $resultMsg = $mapped['result_msg'] ?? null;
        $externalOrderStatus = $mapped['order_status'] ?? null;

        // 2. 通过 linkId 在 forward_orders 的 step_data 中查找
        $forwardOrder = $this->findForwardOrder($linkId, $mobile);

        if (! $forwardOrder) {
            Log::warning('省份回调处理失败：未找到匹配的 ForwardOrder', [
                'callback_log_id' => $log->id,
                'link_id' => $linkId,
                'mobile' => $mobile,
            ]);
            return [
                'success' => false,
                'forward_order_id' => null,
                'product_order_id' => null,
                'error' => '未找到匹配的中转记录',
            ];
        }

        // 3. 根据 success_rule 判定成功/失败
        $isSuccess = $this->evaluateSuccess($params, $successRule);

        // 4. 更新 ForwardOrder 状态
        $this->updateForwardOrder($forwardOrder, $isSuccess, $resultMsg, $mapped);

        // 5. 更新 ProductOrder 状态
        $productOrderId = null;
        if ($isSuccess) {
            $productOrderId = $this->updateProductOrderSuccess($forwardOrder, $mapped);
        } else {
            $productOrderId = $this->updateProductOrderFailed($forwardOrder, $mapped);
        }

        Log::info($isSuccess ? '省份回调处理成功' : '省份回调处理失败', [
            'callback_log_id' => $log->id,
            'forward_order_id' => $forwardOrder->id,
            'link_id' => $linkId,
            'result_code' => $resultCode,
            'is_success' => $isSuccess,
        ]);

        return [
            'success' => true,
            'forward_order_id' => $forwardOrder->id,
            'product_order_id' => $productOrderId,
            'error' => null,
        ];
    }

    /**
     * 根据 field_mapping 将外部参数映射为内部字段
     */
    protected function applyFieldMapping(array $params, array $fieldMapping): array
    {
        $mapped = [];
        foreach ($fieldMapping as $internal => $external) {
            if (isset($params[$external])) {
                $mapped[$internal] = $params[$external];
            }
        }
        return $mapped;
    }

    /**
     * 通过 linkId 查找 ForwardOrder
     * 在 step_data JSON 中搜索 linkId 匹配的记录
     */
    protected function findForwardOrder(?string $linkId, ?string $mobile): ?ForwardOrder
    {
        if ($linkId) {
            // 在 step_data JSON 中搜索 linkId
            $forwardOrder = ForwardOrder::where('step_data->linkId', $linkId)
                ->orWhere('step_data->linkId', (int) $linkId)
                ->latest()
                ->first();

            if ($forwardOrder) {
                return $forwardOrder;
            }
        }

        // 回退：通过 mobile 查找最近的待验证记录
        if ($mobile) {
            return ForwardOrder::where('mobile', $mobile)
                ->where('forward_status', ForwardOrder::STATUS_VERIFYING)
                ->latest()
                ->first();
        }

        return null;
    }

    /**
     * 根据 success_rule 判定回调是否成功
     */
    protected function evaluateSuccess(array $params, ?array $successRule): bool
    {
        if (! $successRule || ! isset($successRule['field'])) {
            return false;
        }

        $fieldValue = $params[$successRule['field']] ?? null;
        $expectedValue = $successRule['value'] ?? null;
        $operator = $successRule['operator'] ?? 'eq';

        return match ($operator) {
            'eq', '==' => $fieldValue == $expectedValue,
            '===' => $fieldValue === $expectedValue,
            'neq', '!=' => $fieldValue != $expectedValue,
            'gt' => $fieldValue > $expectedValue,
            'gte', '>=' => $fieldValue >= $expectedValue,
            'lt' => $fieldValue < $expectedValue,
            'lte', '<=' => $fieldValue <= $expectedValue,
            default => $fieldValue == $expectedValue,
        };
    }

    /**
     * 更新 ForwardOrder 状态
     */
    protected function updateForwardOrder(ForwardOrder $forwardOrder, bool $isSuccess, ?string $resultMsg, array $mapped): void
    {
        $stepResponses = $forwardOrder->step_responses ?? [];
        $stepResponses[] = [
            'step_name' => 'callback',
            'step_index' => 'callback',
            'request' => $mapped,
            'response' => $resultMsg,
            'time' => now()->toDateTimeString(),
        ];

        $updateData = [
            'step_responses' => $stepResponses,
        ];

        if ($isSuccess) {
            $updateData['forward_status'] = ForwardOrder::STATUS_SUCCESS;
        } else {
            $updateData['forward_status'] = ForwardOrder::STATUS_FAILED;
            $updateData['forward_error'] = $resultMsg ?: '运营商回调失败';
        }

        $forwardOrder->update($updateData);
    }

    /**
     * 回调成功时更新 ProductOrder 状态为"首次订购"
     */
    protected function updateProductOrderSuccess(ForwardOrder $forwardOrder, array $mapped): ?int
    {
        $order = ProductOrder::where('order_no', $forwardOrder->source_order_no)
            ->where('pid', $forwardOrder->source_pid)
            ->first();

        if (! $order) {
            return null;
        }

        $oldStatus = $order->order_status;
        $order->order_status = 1; // 首次订购

        // 追加回调日志
        $logEntry = sprintf(
            "\n[%s] 运营商回调 - 成功 (resCode: %s)",
            now()->format('Y-m-d H:i:s'),
            $mapped['result_code'] ?? 'N/A'
        );
        $order->forward_log = ($order->forward_log ?? '') . $logEntry;

        $order->save();

        // 记录状态变更事件
        $statusData = [
            'table' => 'product_order_statuses',
            'order_id' => $order->id,
            'pid' => $forwardOrder->source_pid,
            'order_no' => $order->order_no,
            'old_status' => $oldStatus,
            'new_status' => 1,
            'price' => $order->price,
            'total_amount' => $order->total_amount,
            'operator' => 'PROVINCE_CALLBACK',
            'created_at' => now(),
        ];

        event(new OrderStatusUpdated(
            $order,
            $forwardOrder->sourceChannel,
            false,
            $oldStatus,
            1,
            $statusData
        ));

        return $order->id;
    }

    /**
     * 回调失败时更新 ProductOrder 日志
     */
    protected function updateProductOrderFailed(ForwardOrder $forwardOrder, array $mapped): ?int
    {
        $order = ProductOrder::where('order_no', $forwardOrder->source_order_no)
            ->where('pid', $forwardOrder->source_pid)
            ->first();

        if (! $order) {
            return null;
        }

        $logEntry = sprintf(
            "\n[%s] 运营商回调 - 失败 (resCode: %s, errorCode: %s)",
            now()->format('Y-m-d H:i:s'),
            $mapped['result_code'] ?? 'N/A',
            $mapped['error_code'] ?? 'N/A'
        );
        $order->forward_log = ($order->forward_log ?? '') . $logEntry;
        $order->save();

        return $order->id;
    }
}