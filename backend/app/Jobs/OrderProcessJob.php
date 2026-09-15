<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use App\Models\QuanyuOrder;
use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ChannelFactory;
use App\Services\ThirdChannel\ChannelConfigLoader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * 每次扫描的最大订单数
     */
    const MAX_ORDERS = 100;

    /**
     * 是否处理测试订单
     */
    protected bool $processTestOrders;

    public function __construct(bool $processTestOrders = false)
    {
        $this->processTestOrders = $processTestOrders;
    }

    public function handle(): void
    {
        $modelClass = $this->processTestOrders ? ProductOrderTest::class : ProductOrder::class;
        $env = $this->processTestOrders ? 'test' : 'prod';

        // 1. 查询待处理订单（sync_status=0，未推送）
        $orders = $modelClass::where('sync_status', 0)
            ->orderBy('order_time', 'asc')
            ->limit(self::MAX_ORDERS)
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        Log::info("订单扫描任务：发现 {$orders->count()} 条待处理订单（{$env}）");

        // 2. 标记为处理中，防止重复扫描
        $orderIds = $orders->pluck('id')->toArray();
        $modelClass::whereIn('id', $orderIds)
            ->where('sync_status', 0)
            ->update(['sync_status' => -1]); // -1 表示处理中

        // 3. 逐条处理
        foreach ($orders as $order) {
            try {
                $channel = ThirdChannels::where('pid', $order->pid)->first();
                if (! $channel) {
                    Log::warning('订单跳过：渠道不存在', [
                        'order_no' => $order->order_no,
                        'pid' => $order->pid,
                    ]);
                    $order->update(['sync_status' => 2, 'sync_error' => '渠道不存在']);
                    continue;
                }

                // 检查渠道是否需要推送
                if (! str_contains($channel->method, 'send') && ! str_contains($channel->method, 'forward')) {
                    // 仅接收，无需推送
                    $order->update(['sync_status' => 1]);
                    continue;
                }

                // 根据渠道配置分发推送
                $this->dispatchPush($channel, $order);

            } catch (\Exception $e) {
                Log::error('订单扫描处理失败', [
                    'order_no' => $order->order_no,
                    'error' => $e->getMessage(),
                ]);
                $order->update(['sync_status' => 2, 'sync_error' => $e->getMessage()]);
            }
        }

        Log::info("订单扫描任务完成：处理 {$orders->count()} 条（{$env}）");
    }

    /**
     * 根据渠道配置分发推送任务
     */
    protected function dispatchPush($channel, $order): void
    {
        $data = $order->toArray();

        if ($channel->pid === '186') {
            // 鑫全域渠道：写入 quanyu_orders 并推送
            $this->dispatchQuanyuPush($channel, $data);
        } else {
            // 通用渠道：通过 ChannelFactory
            try {
                $factory = app(ChannelFactory::class);
                $service = $factory->make($channel->pid);
                $service->send($data);
                $order->update(['sync_status' => 1]);
            } catch (\Exception $e) {
                Log::error('通用渠道推送失败', [
                    'pid' => $channel->pid,
                    'order_no' => $order->order_no,
                    'error' => $e->getMessage(),
                ]);
                $order->update(['sync_status' => 2, 'sync_error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 分发鑫全域渠道推送
     */
    protected function dispatchQuanyuPush($channel, array $data): void
    {
        $quanyuOrder = QuanyuOrder::create([
            'mobile' => $data['user_phone'] ?? '',
            'pid' => $channel->pid,
            'bus_code' => $data['bus_code'] ?? '',
            'sku_code' => $data['sku_code'] ?? '',
            'order_no' => $data['order_no'] ?? '',
            'create_time' => $data['order_time'] ?? now(),
            'type' => $data['type'] ?? '1',
            'platform' => $data['platform'] ?? '',
            'pack' => $data['pack'] ?? '',
            'url' => $data['url'] ?? '',
            'ip' => $data['ip'] ?? '',
            'sms_time' => $data['sms_time'] ?? '',
            'code' => $data['code'] ?? '',
            'order_status' => $data['order_status'] ?? 0,
            'price' => $data['price'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'quantity' => $data['quantity'] ?? 1,
            'sync_status' => 0,
            'organization_id' => $channel->organization_id,
        ]);

        $endpoint = '/api_v2/ThirdChannel/syncUserData';
        PushToXinquanyu::dispatch($quanyuOrder->toArray(), $endpoint, $channel->pid);

        Log::info('订单扫描已派发推送任务', [
            'order_no' => $data['order_no'] ?? 'N/A',
            'pid' => $channel->pid,
            'quanyu_order_id' => $quanyuOrder->id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('订单扫描任务异常', [
            'error' => $exception->getMessage(),
        ]);
    }
}