<?php

namespace App\Jobs;

use App\Events\OrderCreated;
use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\SignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [5, 15, 30];

    public function __construct(
        protected array $data,
        protected ThirdChannels $channel,
        protected bool $isTestEnv,
        protected string $clientIp
    ) {}

    public function handle(): void
    {
        $modelClass = $this->isTestEnv ? ProductOrderTest::class : ProductOrder::class;

        // 1. 业务链路校验（产品授权、SKU、业务码）
        $product = $this->verifyProductAccess($this->channel, $this->data);
        if (! $product) {
            Log::warning('订单跳过：未经授权的产品或业务', [
                'pid' => $this->channel->pid,
                'sku_code' => $this->data['sku_code'] ?? '',
                'bus_code' => $this->data['bus_code'] ?? '',
                'order_no' => $this->data['order_no'] ?? '',
            ]);
            return;
        }

        // 2. 写入数据库
        try {
            $quantity = (int) ($this->data['quantity'] ?? 1);
            $internalAmount = $product->base_price * $quantity;

            $order = $modelClass::create(array_merge($this->data, [
                'pid' => $this->channel->pid,
                'sync_status' => 0,
                'ip' => $this->clientIp,
                'order_time' => $this->data['order_time'] ?? now(),
                'internal_amount' => $internalAmount,
                'product_id' => $product->product_id,
                'business_id' => $product->business_id,
                'channel_id' => $product->channel_id,
                'organization_id' => $this->channel->organization_id,
            ]));

            // 3. 触发事件驱动流水线（审计日志、推送等）
            event(new OrderCreated($order, $this->channel, $this->isTestEnv));

            Log::info('订单队列处理成功', [
                'order_no' => $order->order_no,
                'pid' => $this->channel->pid,
                'trace_id' => $this->data['trace_id'] ?? 'N/A',
            ]);

            // 4. 回调通知 A 公司（与转发流程保持一致）
            $this->dispatchCallback($order);
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23000', '2601', '2627'])) {
                Log::warning('订单队列处理跳过：重复订单号', [
                    'order_no' => $this->data['order_no'] ?? '',
                    'pid' => $this->channel->pid,
                ]);
                return;
            }
            throw $e;
        }
    }

    private function verifyProductAccess($channel, array $data)
    {
        $cacheKey = "auth_chain:{$channel->id}:{$data['sku_code']}:{$data['bus_code']}";

        $product = Cache::remember($cacheKey, 3600, function () use ($channel, $data) {
            return DB::table('channel_products')
                ->join('products', 'channel_products.product_id', '=', 'products.id')
                ->join('business', 'products.business_id', '=', 'business.id')
                ->where('channel_products.channel_id', $channel->id)
                ->where('products.sku_code', $data['sku_code'])
                ->where('products.status', 1)
                ->where('business.code', $data['bus_code'])
                ->first(['channel_id', 'product_id', 'business_id', 'base_price']);
        });

        return $product;
    }

    /**
     * 回调通知 A 公司订单处理结果（与转发流程保持一致）
     */
    protected function dispatchCallback($order): void
    {
        $callbackUrl = $this->channel->callback_url;
        if (! $callbackUrl) {
            return;
        }

        try {
            $callbackData = [
                'order_no' => $order->order_no,
                'order_status' => $order->order_status,
                'trace_id' => $this->data['trace_id'] ?? '',
                'pid' => $this->channel->pid,
                'notify_time' => now()->toDateTimeString(),
                'notify_type' => 'order_created',
            ];

            // 生成签名（使用源渠道的签名密钥）
            $signService = app(SignService::class);
            $signConfig = $signService->getConfigForChannel($this->channel);
            $signature = $signService->sign($callbackData, $signConfig['key'], $signConfig['algorithm']);

            Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Sign' => $signature,
                    'X-Sign-Algorithm' => $signConfig['algorithm'],
                    'X-Order-Callback' => 'true',
                ])
                ->post($callbackUrl, $callbackData);

            Log::info('订单回调通知完成', [
                'order_no' => $order->order_no,
                'pid' => $this->channel->pid,
                'callback_url' => $callbackUrl,
            ]);
        } catch (\Exception $e) {
            Log::warning('订单回调通知异常（不影响订单处理）', [
                'order_no' => $order->order_no,
                'pid' => $this->channel->pid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('订单队列处理彻底失败', [
            'order_no' => $this->data['order_no'] ?? '',
            'pid' => $this->channel->pid,
            'error' => $exception->getMessage(),
        ]);

        // 处理失败时也触发回调通知 A 公司
        $this->dispatchCallbackFailed($exception);
    }

    /**
     * 处理失败时回调通知 A 公司
     */
    protected function dispatchCallbackFailed(\Throwable $exception): void
    {
        $callbackUrl = $this->channel->callback_url;
        if (! $callbackUrl) {
            return;
        }

        try {
            $callbackData = [
                'order_no' => $this->data['order_no'] ?? '',
                'order_status' => -1,
                'trace_id' => $this->data['trace_id'] ?? '',
                'pid' => $this->channel->pid,
                'error' => $exception->getMessage(),
                'notify_time' => now()->toDateTimeString(),
                'notify_type' => 'order_failed',
            ];

            $signService = app(SignService::class);
            $signConfig = $signService->getConfigForChannel($this->channel);
            $signature = $signService->sign($callbackData, $signConfig['key'], $signConfig['algorithm']);

            Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Sign' => $signature,
                    'X-Sign-Algorithm' => $signConfig['algorithm'],
                    'X-Order-Callback' => 'true',
                ])
                ->post($callbackUrl, $callbackData);

            Log::info('订单失败回调通知完成', [
                'order_no' => $this->data['order_no'] ?? '',
                'pid' => $this->channel->pid,
            ]);
        } catch (\Exception $e) {
            Log::warning('订单失败回调通知异常', [
                'order_no' => $this->data['order_no'] ?? '',
                'pid' => $this->channel->pid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}