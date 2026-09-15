<?php

namespace App\Jobs;

use App\Models\ForwardOrder;
use App\Services\ThirdChannel\ChannelConfigLoader;
use App\Services\ThirdChannel\SignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 结果回写A公司 Job
 * 数据中转完成后，异步通知源渠道（A公司）处理结果
 */
class NotifyOriginJob implements ShouldQueue
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
        protected int $forwardOrderId
    ) {}

    /**
     * 执行回调通知
     */
    public function handle(
        ChannelConfigLoader $configLoader,
        SignService $signService
    ): void {
        // 1. 加载中转记录
        $forwardOrder = ForwardOrder::find($this->forwardOrderId);
        if (! $forwardOrder) {
            Log::warning('回调任务跳过：中转记录不存在', ['forward_order_id' => $this->forwardOrderId]);
            return;
        }

        // 2. 检查是否已回调过
        if ($forwardOrder->callback_status !== ForwardOrder::CALLBACK_PENDING) {
            Log::info('回调任务跳过：已回调过', [
                'forward_order_id' => $forwardOrder->id,
                'callback_status' => $forwardOrder->callback_status,
            ]);
            return;
        }

        // 3. 检查回调地址
        $callbackUrl = $forwardOrder->callback_url;
        if (! $callbackUrl) {
            Log::warning('回调任务跳过：未配置回调地址', ['forward_order_id' => $forwardOrder->id]);
            return;
        }

        // 4. 构建回调数据
        $callbackData = [
            'forward_id' => $forwardOrder->id,
            'source_order_no' => $forwardOrder->source_order_no,
            'target_order_no' => $forwardOrder->target_order_no,
            'forward_status' => $forwardOrder->forward_status,
            'forward_error' => $forwardOrder->forward_error,
            'target_pid' => $forwardOrder->target_pid,
            'notify_time' => now()->toDateTimeString(),
        ];

        // 5. 生成签名（使用源渠道的签名密钥）
        $sourceChannel = $configLoader->load($forwardOrder->source_pid);
        $signature = null;
        $signAlgorithm = 'sha256';
        if ($sourceChannel) {
            $signConfig = $signService->getConfigForChannel($sourceChannel);
            $signature = $signService->sign($callbackData, $signConfig['key'], $signConfig['algorithm']);
            $signAlgorithm = $signConfig['algorithm'];
        }

        // 6. 发送回调请求
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Sign' => $signature ?? '',
                    'X-Sign-Algorithm' => $signAlgorithm,
                    'X-Forward-Id' => (string) $forwardOrder->id,
                    'X-Callback' => 'true',
                ])
                ->post($callbackUrl, $callbackData);

            $result = $response->json();

            // 7. 判定回调结果
            $callbackStatus = $response->successful()
                ? ForwardOrder::CALLBACK_SUCCESS
                : ForwardOrder::CALLBACK_FAILED;

            $forwardOrder->update([
                'callback_status' => $callbackStatus,
                'callback_response' => $result ?: $response->body(),
            ]);

            Log::info('回调通知完成', [
                'forward_order_id' => $forwardOrder->id,
                'source_order_no' => $forwardOrder->source_order_no,
                'callback_status' => $callbackStatus,
                'http_status' => $response->status(),
            ]);

            // 回调失败时抛出异常触发重试
            if (! $response->successful()) {
                throw new \Exception("回调返回非成功状态码: {$response->status()}");
            }
        } catch (\Exception $e) {
            $forwardOrder->update([
                'callback_status' => ForwardOrder::CALLBACK_FAILED,
                'callback_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('回调通知异常', [
                'forward_order_id' => $forwardOrder->id,
                'source_order_no' => $forwardOrder->source_order_no,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 最终失败处理
     */
    public function failed(\Throwable $exception): void
    {
        $forwardOrder = ForwardOrder::find($this->forwardOrderId);
        if ($forwardOrder) {
            $forwardOrder->update([
                'callback_status' => ForwardOrder::CALLBACK_FAILED,
                'callback_response' => array_merge(
                    (array) ($forwardOrder->callback_response ?? []),
                    ['final_error' => $exception->getMessage()]
                ),
            ]);
        }

        Log::critical('回调通知彻底失败，已停止重试', [
            'forward_order_id' => $this->forwardOrderId,
            'final_error' => $exception->getMessage(),
        ]);
    }
}