<?php

namespace App\Services\ThirdChannel\Interpreters;

use App\Models\CallbackLog;
use App\Models\ProductOrder;
use App\Services\ThirdChannel\CallbackInterpreter;
use Illuminate\Support\Facades\Log;

class DefaultCallbackInterpreter implements CallbackInterpreter
{
    /**
     * 默认回调解释器
     * 与现有 CallbackController::callback() 逻辑一致
     * 适用于通用的 order_no + result_status 格式
     */
    public function interpret(CallbackLog $log, array $config): array
    {
        $params = $log->request_params ?? [];
        $fieldMapping = $config['field_mapping'] ?? [];
        $successRule = $config['success_rule'] ?? null;

        // 根据 field_mapping 提取内部字段
        $mapped = $this->applyFieldMapping($params, $fieldMapping);

        $orderNo = $mapped['order_no'] ?? null;
        $resultStatus = $mapped['result_status'] ?? null;
        $resultMsg = $mapped['result_msg'] ?? null;

        if (! $orderNo || ! $resultStatus) {
            return [
                'success' => false,
                'forward_order_id' => null,
                'product_order_id' => null,
                'error' => '缺少必要参数: order_no, result_status',
            ];
        }

        // 查找订单
        $order = ProductOrder::where('order_no', $orderNo)
            ->where('pid', $log->channel_pid)
            ->first();

        if (! $order) {
            return [
                'success' => false,
                'forward_order_id' => null,
                'product_order_id' => null,
                'error' => '订单不存在',
            ];
        }

        $isSuccess = $this->evaluateSuccess($params, $successRule);

        $order->update([
            'sync_status' => $isSuccess ? 1 : 2,
            'sync_error' => $isSuccess ? null : $resultMsg,
            'pushed_at' => $isSuccess ? now() : $order->pushed_at,
        ]);

        Log::info('通用回调处理完成', [
            'callback_log_id' => $log->id,
            'order_no' => $orderNo,
            'is_success' => $isSuccess,
        ]);

        return [
            'success' => true,
            'forward_order_id' => null,
            'product_order_id' => $order->id,
            'error' => null,
        ];
    }

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
            default => $fieldValue == $expectedValue,
        };
    }
}