<?php

namespace App\Services\ThirdChannel;

use App\Models\ForwardOrder;
use App\Models\ThirdChannels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 同步转发服务
 * 封装同步执行转发步骤的核心逻辑，供 ReceiverController 在请求上下文中直接调用
 * 区别于 ForwardOrderJob（异步队列），本服务在 A 公司请求等待期间同步执行
 */
class ForwardService
{
    public function __construct(
        protected ChannelConfigLoader $configLoader,
        protected DataMapper $dataMapper,
        protected SignService $signService
    ) {}

    /**
     * 同步执行指定步骤的转发
     *
     * @param  ForwardOrder  $forwardOrder  转发记录
     * @param  int  $stepIndex  步骤索引（0=getCode, 1=submit）
     * @return array  ['success' => bool, 'step_data' => array, 'result' => array|null, 'error' => string|null]
     */
    public function executeStep(ForwardOrder $forwardOrder, int $stepIndex): array
    {
        // 1. 加载目标渠道
        $targetChannel = $this->configLoader->load($forwardOrder->target_pid);
        if (! $targetChannel) {
            return ['success' => false, 'error' => '目标渠道不存在: '.$forwardOrder->target_pid];
        }

        // 2. 获取步骤配置
        $steps = $targetChannel->push_steps ?? [];
        if (empty($steps) || ! isset($steps[$stepIndex])) {
            return ['success' => false, 'error' => "步骤 {$stepIndex} 配置不存在"];
        }
        $step = $steps[$stepIndex];
        $stepName = $step['name'] ?? "step_{$stepIndex}";

        // 3. 获取推送 URL
        $pushUrl = $this->configLoader->getStepUrl($targetChannel, $step);
        if (! $pushUrl) {
            return ['success' => false, 'error' => '目标渠道未配置推送地址'];
        }

        // 4. 合并数据：source_data（A 公司原始数据）+ step_data（之前步骤的输出）
        $sourceData = $forwardOrder->source_data ?? [];
        $stepData = $forwardOrder->step_data ?? [];
        $mergedData = array_merge($sourceData, $stepData);

        // 5. 应用请求映射
        $requestMapping = $step['request_mapping'] ?? null;
        if ($requestMapping) {
            $transformedData = $this->dataMapper->mapForPush($mergedData, $requestMapping);
        } else {
            $transformedData = $mergedData;
        }

        // 注入追踪标识
        $transformedData['_forward_id'] = $forwardOrder->id;
        $transformedData['_source_order_no'] = $forwardOrder->source_order_no;

        Log::info('同步转发映射完成', [
            'forward_order_id' => $forwardOrder->id,
            'step_index' => $stepIndex,
            'step_name' => $stepName,
            'target_pid' => $forwardOrder->target_pid,
            'push_url' => $pushUrl,
            'fields_count' => count($transformedData),
            'transformed_data' => $transformedData,
        ]);

        // 6. 生成签名
        $signature = null;
        $signAlgorithm = 'sha256';
        $signConfig = $this->signService->getConfigForChannel($targetChannel);
        if ($signConfig['key'] ?? null) {
            $signature = $this->signService->sign($transformedData, $signConfig['key'], $signConfig['algorithm']);
            $signAlgorithm = $signConfig['algorithm'];
        }

        // 7. 发送 HTTP 请求（同步等待 B 回复）
        try {
            $timeout = $this->configLoader->getPushTimeout($targetChannel);
            $http = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ]);

            if ($signature) {
                $http = $http->withHeaders([
                    'X-Sign' => $signature,
                    'X-Sign-Algorithm' => $signAlgorithm,
                ]);
            }

            // 按步骤配置的请求方式发送：GET 走 query 串，其余默认 POST 走 JSON body
            $requestMethod = strtoupper($step['method'] ?? 'POST');
            if ($requestMethod === 'GET') {
                $response = $http->get($pushUrl, $transformedData);
            } else {
                $response = $http->post($pushUrl, $transformedData);
            }
            $result = $response->json();
            $rawBody = $response->body();

            Log::debug('移动原始响应', [
                'forward_order_id' => $forwardOrder->id,
                'step_index' => $stepIndex,
                'http_status' => $response->status(),
                'response_body' => $result ?? $rawBody,
            ]);

            // 8. 判定结果
            if ($response->successful()) {
                // 响应体非 JSON 时，HTTP 200 直接视为成功（无业务规则可匹配）
                if (! is_array($result)) {
                    Log::info('同步转发步骤成功（HTTP 200，响应体非JSON，按默认规则视为成功）', [
                        'forward_order_id' => $forwardOrder->id,
                        'step_index' => $stepIndex,
                        'step_name' => $stepName,
                        'raw_body' => mb_substr($rawBody, 0, 500),
                    ]);

                    return [
                        'success' => true,
                        'step_data' => [],
                        'result' => null,
                        'step_name' => $stepName,
                        'b_response' => $rawBody,
                    ];
                }

                // 8a. 业务层面判定（检查 success_rule / fail_rule / 默认 code=0）
                Log::debug('业务判定调试', [
                    'step_index' => $stepIndex,
                    'step_name' => $stepName,
                    'success_rule' => $step['success_rule'] ?? null,
                    'fail_rule' => $step['fail_rule'] ?? null,
                    'data_code' => data_get($result, 'data.code'),
                    'result_code' => $result['code'] ?? null,
                    'result_keys' => array_keys($result),
                ]);
                $businessSuccess = $this->evaluateResponseSuccess($result, $step, $targetChannel);
                Log::debug('业务判定结果', [
                    'step_index' => $stepIndex,
                    'businessSuccess' => $businessSuccess,
                ]);

                if ($businessSuccess) {
                    // 提取输出数据
                    $outputData = $this->extractOutputData($result, $step);

                    Log::info('同步转发步骤成功', [
                        'forward_order_id' => $forwardOrder->id,
                        'step_index' => $stepIndex,
                        'step_name' => $stepName,
                        'output_data' => $outputData,
                        'b_response' => $result,
                    ]);

                    return [
                        'success' => true,
                        'step_data' => $outputData,
                        'result' => $result,       // 移动的完整响应
                        'step_name' => $stepName,
                        'b_response' => $result,   // 额外字段，方便取用
                    ];
                }

                // 8b. HTTP 200 但业务判定失败 → 提取错误信息
                $errorMsg = $this->extractBusinessError($result, $step, $targetChannel);
                $errorCode = $this->extractBusinessErrorCode($result);

                Log::warning('同步转发业务失败', [
                    'forward_order_id' => $forwardOrder->id,
                    'step_index' => $stepIndex,
                    'step_name' => $stepName,
                    'error' => $errorMsg,
                    'error_code' => $errorCode,
                    'response' => $result,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMsg,
                    'error_code' => $errorCode,
                    'result' => $result,
                    'step_name' => $stepName,
                    'b_response' => $result,
                ];
            } else {
                $errorMsg = "移动接口返回非成功状态码: {$response->status()}";

                Log::error('同步转发网络失败', [
                    'forward_order_id' => $forwardOrder->id,
                    'step_index' => $stepIndex,
                    'step_name' => $stepName,
                    'http_status' => $response->status(),
                    'response' => $result,
                ]);

                return ['success' => false, 'error' => $errorMsg, 'error_code' => null];
            }
        } catch (\Exception $e) {
            $errorMsg = "{$stepName} 请求异常: ".$e->getMessage();

            Log::error('同步转发网络异常', [
                'forward_order_id' => $forwardOrder->id,
                'step_index' => $stepIndex,
                'step_name' => $stepName,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $errorMsg, 'error_code' => null];
        }
    }

    /**
     * 从业务响应中提取错误码
     * 支持递减路径：data.code / code / data.data.code / data.data.resultCode ... 递归深入 data 嵌套层查找
     */
    protected function extractBusinessErrorCode(array $result): ?string
    {
        // 直接路径优先（code/resultCode/errorCode，不把 state 当错误码）
        $code = $this->findFirstScalar($result, ['code', 'resultCode', 'errorCode'])
            ?? $this->findCodeDeep($result['data'] ?? null);

        if ($code !== null) {
            $code = (string) $code;
            // state=success 不应视为错误码
            if (in_array($code, ['success', '00000', '0'])) {
                return null;
            }
        }

        return $code !== null ? (string) $code : null;
    }

    /**
     * 在给定数组中按 key 列表取首个标量值
     */
    protected function findFirstScalar(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if ($value !== null && ! is_array($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * 递归深入 data 嵌套数组，查找 code/resultCode 等错误码标量
     */
    protected function findCodeDeep(mixed $node): mixed
    {
        if (! is_array($node)) {
            return null;
        }
        foreach (['code', 'resultCode', 'errorCode'] as $key) {
            $value = $node[$key] ?? null;
            if ($value !== null && ! is_array($value)) {
                return $value;
            }
        }
        // 向第一层 data/data.data 深入（保持嵌套路径语义，优先最深层）
        foreach ($node as $key => $sub) {
            if (is_array($sub) && \Illuminate\Support\Arr::isAssoc($sub)) {
                $found = $this->findCodeDeep($sub);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * 从响应中提取输出数据（供下一步使用）
     */
    protected function extractOutputData(array $result, array $step): array
    {
        $output = [];

        if (isset($step['output_mapping'])) {
            foreach ($step['output_mapping'] as $targetKey => $sourcePath) {
                $extracted = data_get($result, $sourcePath);
                if ($extracted !== null) {
                    $output[$targetKey] = $extracted;
                }
            }
        } else {
            // 默认提取逻辑：getCode 步骤的响应中提取 linkId
            $linkId = $result['data']['linkId'] ?? $result['linkId'] ?? null;
            if ($linkId) {
                $output['linkId'] = $linkId;
            }
        }

        return $output;
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
    protected function evaluateResponseSuccess(array $result, array $step, ThirdChannels $channel): bool
    {
        // 1. 步骤级规则（优先级最高）
        if (isset($step['success_rule'])) {
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
     * 从业务响应中提取错误信息
     * 按优先级尝试：data.message → message → data.msg → data.error → 响应体中的code组合
     */
    protected function extractBusinessError(array $result, array $step, ThirdChannels $channel): string
    {
        // 按优先级尝试提取错误信息
        $message = data_get($result, 'data.message')
            ?? data_get($result, 'message')
            ?? data_get($result, 'data.msg')
            ?? data_get($result, 'data.error')
            ?? data_get($result, 'error')
            ?? null;

        $code = $this->extractBusinessErrorCode($result);

        if ($message) {
            return $code ? "移动业务错误({$code}): {$message}" : "移动业务错误: {$message}";
        }

        if ($code && ! in_array($code, ['success', '00000', '0'])) {
            return "移动业务错误, code: {$code}";
        }

        return '移动业务返回未通过验证';
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
            'exists' => $fieldValue !== null,
            'not_exists' => $fieldValue === null,
            default => $fieldValue == $expectedValue,
        };
    }
}