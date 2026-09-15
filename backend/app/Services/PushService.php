<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 订单推送服务
 * 统一手动推送和定时推送的 HTTP 请求 + 结果判定逻辑
 */
class PushService
{
    /**
     * 执行推送请求
     *
     * @param  array  $data  推送数据
     * @param  string  $url  推送地址
     * @param  array|null  $successRule  成功判定规则（可选）
     * @param  array|null  $failRule  失败判定规则（可选）
     * @param  int  $timeout  超时秒数
     * @return array{success: bool, http_status: int, body: mixed}
     */
    public function send(array $data, string $url, ?array $successRule = null, ?array $failRule = null, int $timeout = 10): array
    {
        $orderNo = $data['order_no'] ?? 'N/A';
        $startTime = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $data);

            $cost = round((microtime(true) - $startTime) * 1000);

            $result = $response->json() ?? [];
            $httpStatus = $response->status();

            // 1. 优先匹配成功规则
            if ($this->matchRule($result, $successRule)) {
                $this->logDetail($orderNo, $url, $data, $httpStatus, $result, true, $cost, 'success_rule');
                return [
                    'success' => true,
                    'http_status' => $httpStatus,
                    'body' => $result,
                ];
            }

            // 2. 匹配失败规则
            if ($this->matchRule($result, $failRule)) {
                $this->logDetail($orderNo, $url, $data, $httpStatus, $result, false, $cost, 'fail_rule');
                return [
                    'success' => false,
                    'http_status' => $httpStatus,
                    'body' => $result,
                ];
            }

            // 3. 默认规则：HTTP 2xx 视为成功
            $success = $httpStatus >= 200 && $httpStatus < 300;
            $this->logDetail($orderNo, $url, $data, $httpStatus, $result, $success, $cost, 'http_default');
            return [
                'success' => $success,
                'http_status' => $httpStatus,
                'body' => $result,
            ];

        } catch (\Exception $e) {
            $cost = round((microtime(true) - $startTime) * 1000);
            $this->logDetail($orderNo, $url, $data, 0, ['error' => $e->getMessage()], false, $cost, 'exception');
            return [
                'success' => false,
                'http_status' => 0,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * 记录每条推送的详细请求/响应日志
     */
    protected function logDetail(string $orderNo, string $url, array $data, int $httpStatus, array $body, bool $success, float $costMs, string $judge): void
    {
        Log::channel('push_detail')->info('推送记录', [
            'order_no' => $orderNo,
            'url' => $url,
            'success' => $success,
            'judge' => $judge,
            'http_status' => $httpStatus,
            'cost_ms' => $costMs,
            'request' => $data,
            'response' => $body,
            'time' => now()->toDateTimeString(),
        ]);
    }

    /**
     * 匹配单条规则
     */
    protected function matchRule(array $result, ?array $rule): bool
    {
        if (! $rule || ! isset($rule['field'])) {
            return false;
        }

        $fieldValue = data_get($result, $rule['field']);
        $expectedValue = $rule['value'] ?? null;
        $operator = $rule['operator'] ?? 'eq';

        return $this->compareValue($fieldValue, $expectedValue, $operator);
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
}