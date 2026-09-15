<?php

namespace App\Jobs;

use App\Models\QuanyuOrder;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ChannelConfigLoader;
use App\Services\ThirdChannel\DataMapper;
use App\Services\ThirdPartyApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 异步推送任务：将订单数据推送到另一家公司
 * 支持多步骤推送（如：先获取验证码，再提交订单）
 * 支持单步骤推送（向后兼容）
 * 推送地址从渠道配置读取，不再硬编码
 * 重试策略从渠道配置的 push_max_retries / push_retry_delay 读取
 */
class PushToXinquanyu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务数据
     */
    public function __construct(
        protected array $data,
        protected string $endpoint,
        protected ?string $channelPid = null
    ) {
        $this->loadRetryConfig();
    }

    /**
     * 加载渠道配置的重试策略
     * 从数据库读取 push_max_retries 和 push_retry_delay 字段
     */
    protected function loadRetryConfig(): void
    {
        $this->maxRetries = 3;
        $this->retryDelay = 10;
        $this->tries = 4; // 默认：3次重试 + 1次初始尝试

        if (! $this->channelPid) {
            return;
        }

        try {
            $configLoader = app(ChannelConfigLoader::class);
            $channel = $configLoader->load($this->channelPid);
            if ($channel) {
                $retryConfig = $configLoader->getPushRetryConfig($channel);
                $this->maxRetries = $retryConfig['max_retries'];
                $this->retryDelay = $retryConfig['retry_delay'];
                $this->tries = $this->maxRetries + 1; // 总尝试次数 = retries + 1
            }
        } catch (\Exception $e) {
            Log::warning('读取推送重试配置失败，使用默认值', [
                'pid' => $this->channelPid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 任务最大重试次数（总尝试次数 = push_max_retries + 1）
     * 构造函数中动态设置
     *
     * @var int
     */
    public $tries = 5;

    /**
     * 任务重试间隔（秒）
     * 根据渠道配置的 push_retry_delay 动态生成
     * 策略：每次重试等待相同的间隔时间
     */
    public function backoff(): array
    {
        if (! isset($this->maxRetries)) {
            $this->loadRetryConfig();
        }

        $delays = [];
        for ($i = 0; $i < $this->maxRetries; $i++) {
            $delays[] = $this->retryDelay;
        }
        return $delays;
    }

    /** @var int 最大重试次数（从渠道配置读取） */
    protected int $maxRetries;

    /** @var int 重试间隔秒数（从渠道配置读取） */
    protected int $retryDelay;

    /**
     * 执行推送逻辑
     */
    public function handle(): void
    {
        // 1. 获取最新的数据库记录
        $order = QuanyuOrder::where('order_no', $this->data['order_no'])->first();

        // 2. 前置检查
        if (! $order) {
            Log::warning('推送任务跳过：订单数据不存在', ['order_no' => $this->data['order_no']]);
            return;
        }

        if ($order->sync_status === -1) {
            Log::info('推送任务拦截：该订单已被管理员手动取消同步', ['order_no' => $order->order_no]);
            return;
        }

        // 3. 加载渠道配置
        $channel = $this->loadChannel();
        if (! $channel) {
            Log::error('推送任务失败：无法加载渠道配置', ['pid' => $this->channelPid]);
            $order->update(['sync_status' => 2, 'sync_error' => '无法加载渠道配置']);
            return;
        }

        // 4. 推送速率控制
        if (! $this->checkRateLimit()) {
            Log::info('推送速率限制触发，任务延迟重试', [
                'order_no' => $order->order_no,
                'channel_pid' => $this->channelPid,
            ]);
            $this->release(30);
            return;
        }

        // 5. 判断使用多步骤还是单步骤推送
        $steps = $channel->push_steps ?? [];

        if (! empty($steps) && is_array($steps)) {
            $this->handleMultiStep($order, $channel);
        } else {
            $this->handleSingleStep($order, $channel);
        }

        // 6. 推送完成后，通知下家（push_notify_url）
        $this->notifyPushResult($order, $channel);
    }

    /**
     * 多步骤推送流程
     * 依次执行每个步骤，将上一步的输出映射传递到下一步的请求参数
     */
    protected function handleMultiStep(QuanyuOrder $order, $channel): void
    {
        $steps = $channel->push_steps ?? [];
        $allResponses = [];
        $context = $this->data; // 上下文：初始为原始订单数据

        $baseUrl = rtrim($channel->push_base_url ?? '', '/');

        foreach ($steps as $i => $step) {
            $stepName = $step['name'] ?? "步骤{$i}";
            $endpoint = $step['endpoint'] ?? '';
            $method = strtolower($step['method'] ?? 'post');
            $timeout = $step['timeout'] ?? $channel->push_timeout ?? 10;

            if (empty($endpoint)) {
                throw new \Exception("多步骤推送：第{$i}步缺少 endpoint");
            }

            $url = $baseUrl ? $baseUrl . '/' . ltrim($endpoint, '/') : $endpoint;

            // 构建请求参数
            $requestData = $this->buildStepRequest($step, $context);

            Log::info("多步骤推送 - {$stepName}", [
                'order_no' => $order->order_no,
                'url' => $url,
                'method' => $method,
                'step' => $i,
            ]);

            // 发送HTTP请求
            try {
                $http = Http::timeout($timeout)
                    ->withHeaders(['Content-Type' => 'application/json']);

                $response = $http->{$method}($url, $requestData);
                $result = $response->json();
                $allResponses[] = [
                    'step' => $i,
                    'name' => $stepName,
                    'endpoint' => $endpoint,
                    'request' => $requestData,
                    'response' => $result,
                    'http_status' => $response->status(),
                ];

                // 评估当前步骤结果
                $isSuccess = $this->evaluateStepResult($result, $step, 'success');
                $isFail = $this->evaluateStepResult($result, $step, 'fail');

                if ($isFail || (! $isSuccess && ! $this->evaluateDefaultResult($result))) {
                    $errorMsg = $result['msg'] ?? $result['message'] ?? "步骤{$i}失败";
                    Log::error("多步骤推送失败 - {$stepName}", [
                        'order_no' => $order->order_no,
                        'response' => $result,
                    ]);

                    // 记录所有步骤的响应
                    $order->update([
                        'push_response' => $allResponses,
                        'push_response_at' => now(),
                        'sync_status' => 2,
                        'sync_error' => "{$stepName}: {$errorMsg}",
                    ]);

                    throw new \Exception("MultiStep Error: {$errorMsg}");
                }

                // 提取输出映射，供下一步使用
                if (isset($step['output_mapping']) && is_array($step['output_mapping'])) {
                    foreach ($step['output_mapping'] as $key => $path) {
                        $context["step{$i}.{$key}"] = data_get($result, $path);
                    }
                }

                Log::info("多步骤推送成功 - {$stepName}", [
                    'order_no' => $order->order_no,
                    'response' => $result,
                ]);

            } catch (\Exception $e) {
                // 记录到当前步骤的响应
                $allResponses[] = [
                    'step' => $i,
                    'name' => $stepName,
                    'endpoint' => $endpoint,
                    'request' => $requestData,
                    'error' => $e->getMessage(),
                ];
                $order->update([
                    'push_response' => $allResponses,
                    'push_response_at' => now(),
                    'sync_status' => 2,
                    'sync_error' => "{$stepName}: " . $e->getMessage(),
                ]);
                throw $e;
            }
        }

        // 所有步骤成功
        $order->update([
            'push_response' => $allResponses,
            'push_response_at' => now(),
            'sync_status' => 1,
            'updated_at' => now(),
        ]);

        Log::info('多步骤推送全部完成', [
            'order_no' => $order->order_no,
            'steps' => count($steps),
        ]);
    }

    /**
     * 单步骤推送流程（向后兼容）
     */
    protected function handleSingleStep(QuanyuOrder $order, $channel): void
    {
        $pushUrl = $this->getPushUrl();
        if (! $pushUrl) {
            Log::error('推送任务失败：未配置推送地址', ['order_no' => $order->order_no]);
            $order->update(['sync_status' => 2, 'sync_error' => '未配置推送地址']);
            return;
        }

        // 准备签名
        $appKey = ThirdPartyApiService::getKeyForChannel($this->channelPid ?? '')
            ?? config('services.partner.key', '1252KS25D7F3ZC7J');
        $sign = ThirdPartyApiService::generateSign($this->data, $appKey);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'sign' => $sign,
                    'Accept' => 'application/json',
                ])
                ->post($pushUrl, $this->data);

            $result = $response->json();

            // 记录推送返回信息
            $order->update([
                'push_response' => $result,
                'push_response_at' => now(),
            ]);

            // 使用配置化规则判定
            $isSuccess = $this->evaluatePushResult($result, 'success');
            $isFail = $this->evaluatePushResult($result, 'fail');

            if ($isSuccess) {
                $order->update(['sync_status' => 1, 'updated_at' => now()]);
                Log::info('第三方推送成功', ['order_no' => $order->order_no, 'response' => $result]);
            } elseif ($isFail) {
                $order->update(['sync_status' => 2]);
                $errorMsg = $result['msg'] ?? $result['message'] ?? 'Unknown Error';
                Log::error('第三方推送业务失败', [
                    'order_no' => $order->order_no,
                    'status' => $response->status(),
                    'response' => $result,
                ]);
                throw new \Exception('Partner API Error: '.$errorMsg);
            } else {
                // 默认规则
                if (isset($result['code']) && $result['code'] === 0) {
                    $order->update(['sync_status' => 1, 'updated_at' => now()]);
                    Log::info('第三方推送成功(默认规则)', ['order_no' => $order->order_no, 'response' => $result]);
                } else {
                    $order->update(['sync_status' => 2]);
                    $errorMsg = $result['msg'] ?? $result['message'] ?? 'Unknown Error';
                    Log::error('第三方推送业务失败(默认规则)', [
                        'order_no' => $order->order_no,
                        'status' => $response->status(),
                        'response' => $result,
                    ]);
                    throw new \Exception('Partner API Error: '.$errorMsg);
                }
            }

        } catch (\Exception $e) {
            $order->update(['sync_status' => 2]);
            Log::error('推送任务异常(网络/连接)', [
                'order_no' => $this->data['order_no'],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 构建步骤请求参数
     * 将 request_mapping 中的配置与上下文数据合并
     * 支持 {stepN.key} 占位符引用上一步输出
     * 委托给 DataMapper::mapForPush 实现统一逻辑
     */
    protected function buildStepRequest(array $step, array $context): array
    {
        $mapping = $step['request_mapping'] ?? null;

        // 未配置 request_mapping，直接使用原始数据
        if (! $mapping) {
            return $context;
        }

        return app(DataMapper::class)->mapForPush($context, $mapping);
    }

    /**
     * 使用步骤自身的规则评估结果
     */
    protected function evaluateStepResult(array $result, array $step, string $type): bool
    {
        $ruleKey = $type === 'success' ? 'success_rule' : 'fail_rule';
        $rule = $step[$ruleKey] ?? null;

        if (! $rule || ! isset($rule['field'])) {
            return false;
        }

        $fieldValue = data_get($result, $rule['field']);
        $expectedValue = $rule['value'] ?? null;
        $operator = $rule['operator'] ?? 'eq';

        return $this->compareValue($fieldValue, $expectedValue, $operator);
    }

    /**
     * 默认规则：code=0 为成功
     */
    protected function evaluateDefaultResult(array $result): bool
    {
        return isset($result['code']) && $result['code'] === 0;
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

    // ====== 以下方法与之前保持一致 ======

    /**
     * 推送速率控制
     */
    protected function checkRateLimit(): bool
    {
        $channelPid = $this->channelPid ?? 'default';
        $cacheKey = "rate_limit:push:{$channelPid}";

        try {
            $configLoader = app(ChannelConfigLoader::class);
            $channel = $configLoader->load($channelPid);
            $extConfig = $channel->ext_config ?? [];
            $rateLimitConfig = $extConfig['rate_limit'] ?? [];
        } catch (\Exception $e) {
            $rateLimitConfig = [];
        }

        $enabled = $rateLimitConfig['enabled'] ?? true;
        if (! $enabled) {
            return true;
        }

        $maxRequests = $rateLimitConfig['max_requests'] ?? 60;
        $timeWindow = $rateLimitConfig['time_window'] ?? 60;

        $current = Cache::get($cacheKey, 0);
        if ($current >= $maxRequests) {
            return false;
        }

        if ($current === 0) {
            Cache::put($cacheKey, 1, $timeWindow);
        } else {
            Cache::increment($cacheKey);
        }

        return true;
    }

    /**
     * 获取推送完整 URL
     */
    protected function getPushUrl(): ?string
    {
        if ($this->channelPid) {
            try {
                $configLoader = app(ChannelConfigLoader::class);
                $channel = $configLoader->load($this->channelPid);
                if ($channel) {
                    $url = $configLoader->getPushUrl($channel);
                    if ($url) {
                        return $url;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('读取渠道配置失败，使用默认地址', [
                    'pid' => $this->channelPid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($this->endpoint) {
            return 'http://159.75.226.248:8080/'.$this->endpoint;
        }

        return null;
    }

    /**
     * 加载渠道配置
     */
    protected function loadChannel(): ?\App\Models\ThirdChannels
    {
        if (! $this->channelPid) {
            return null;
        }

        try {
            $configLoader = app(ChannelConfigLoader::class);
            return $configLoader->load($this->channelPid);
        } catch (\Exception $e) {
            Log::warning('加载渠道配置失败', [
                'pid' => $this->channelPid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 根据渠道配置的成功/失败规则评估推送结果（单步骤用）
     */
    protected function evaluatePushResult(array $result, string $type): bool
    {
        $rule = $this->getPushRule($type);
        if (! $rule || ! isset($rule['field'])) {
            return false;
        }

        $fieldValue = data_get($result, $rule['field']);
        $expectedValue = $rule['value'] ?? null;
        $operator = $rule['operator'] ?? 'eq';

        return $this->compareValue($fieldValue, $expectedValue, $operator);
    }

    /**
     * 获取推送成功/失败判断规则（单步骤用）
     */
    protected function getPushRule(string $type): ?array
    {
        if (! $this->channelPid) {
            return null;
        }

        try {
            $configLoader = app(ChannelConfigLoader::class);
            $channel = $configLoader->load($this->channelPid);
            if (! $channel) {
                return null;
            }

            $ruleKey = $type === 'success' ? 'push_success_rule' : 'push_fail_rule';
            return $channel->{$ruleKey} ?? null;
        } catch (\Exception $e) {
            Log::warning('读取推送规则失败', [
                'pid' => $this->channelPid,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 推送完成后，通知下家处理结果
     * 发送 POST 请求到渠道配置的 push_notify_url
     * 异步通知，不阻塞推送主流程
     */
    protected function notifyPushResult(QuanyuOrder $order, $channel): void
    {
        $pushNotifyUrl = $channel->push_notify_url ?? null;
        if (! $pushNotifyUrl) {
            return;
        }

        $notifyData = [
            'order_no' => $order->order_no,
            'sync_status' => $order->sync_status,
            'sync_error' => $order->sync_error,
            'pushed_at' => now()->toDateTimeString(),
            'channel_pid' => $this->channelPid,
        ];

        try {
            Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->post($pushNotifyUrl, $notifyData);

            Log::info('已通知下家推送结果', [
                'order_no' => $order->order_no,
                'channel_pid' => $this->channelPid,
                'push_notify_url' => $pushNotifyUrl,
                'sync_status' => $order->sync_status,
            ]);
        } catch (\Exception $e) {
            Log::warning('通知下家推送结果失败', [
                'order_no' => $order->order_no,
                'channel_pid' => $this->channelPid,
                'push_notify_url' => $pushNotifyUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 当任务达到最大重试次数仍然失败时执行
     */
    public function failed(\Throwable $exception): void
    {
        $order = QuanyuOrder::where('order_no', $this->data['order_no'])->first();
        if ($order) {
            $order->update(['sync_status' => 2]);
        }

        Log::critical('订单推送彻底失败，已停止所有重试', [
            'order_no' => $this->data['order_no'],
            'final_error' => $exception->getMessage(),
        ]);
    }
}