<?php

namespace App\Console\Commands;

use App\Models\ForwardOrder;
use App\Models\ProductOrder;
use App\Models\ThirdChannels;
use App\Services\PushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PushPendingCallbacks extends Command
{
    protected $signature = 'callback:push-pending';
    protected $description = '扫描所有启用了定时回调推送的渠道，将待回调的ForwardOrder推送到来源系统(A)';

    public function handle(): int
    {
        try {
            return $this->process();
        } catch (\Throwable $e) {
            Log::channel('push_failure')->error('定时回调推送任务异常终止', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('任务异常终止: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function process(): int
    {
        $this->info('开始扫描待回调订单...');

        $channels = ThirdChannels::where('auto_callback_enabled', true)->get();

        if ($channels->isEmpty()) {
            $this->info('没有启用了定时回调推送的渠道，跳过');
            return self::SUCCESS;
        }

        $now = now()->format('H:i');
        $totalProcessed = 0;
        $totalPushed = 0;

        foreach ($channels as $channel) {
            // 检查时间段
            $start = $channel->auto_callback_time_start;
            $end = $channel->auto_callback_time_end;
            if ($start && $end) {
                if ($now < $start || $now > $end) {
                    $this->line("  渠道 [{$channel->name}] 不在执行时间段内({$start}-{$end})，跳过");
                    continue;
                }
            }

            // 扫描该渠道待回调的 ForwardOrder（仅最近 48 小时）
            $orders = ForwardOrder::where('source_pid', $channel->pid)
                ->where('callback_status', ForwardOrder::CALLBACK_PENDING)
                ->where('created_at', '>=', now()->subHours(48))
                ->get();

            if ($orders->isEmpty()) {
                continue;
            }

            $this->info("渠道 [{$channel->name}] (PID: {$channel->pid}) 发现 {$orders->count()} 条待回调订单");

            foreach ($orders as $forwardOrder) {
                $totalProcessed++;

                try {
                    $result = $this->pushCallback($forwardOrder, $channel);
                    if ($result) {
                        $totalPushed++;
                        $this->line("  ✓ order_no={$forwardOrder->source_order_no} 推送成功");
                    } else {
                        $this->warn("  ✗ order_no={$forwardOrder->source_order_no} 推送失败");
                    }
                } catch (\Exception $e) {
                    Log::channel('push_failure')->error('定时回调推送异常', [
                        'forward_order_id' => $forwardOrder->id,
                        'order_no' => $forwardOrder->source_order_no,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("  ✗ order_no={$forwardOrder->source_order_no} 异常: {$e->getMessage()}");
                }
            }
        }

        $this->info("完成。共处理 {$totalProcessed} 条，成功 {$totalPushed} 条");

        Log::info('定时回调推送任务完成', [
            'total_processed' => $totalProcessed,
            'total_pushed' => $totalPushed,
        ]);

        return self::SUCCESS;
    }

    protected function pushCallback(ForwardOrder $forwardOrder, ThirdChannels $channel): bool
    {
        // 优先使用 ForwardOrder 的 callback_url，回退到渠道配置
        $callbackUrl = $forwardOrder->callback_url ?: $channel->callback_url;
        if (! $callbackUrl) {
            Log::channel('push_failure')->warning('定时回调：无推送地址', [
                'forward_order_id' => $forwardOrder->id,
                'order_no' => $forwardOrder->source_order_no,
            ]);
            return false;
        }

        // 查找产品订单
        $order = ProductOrder::where('order_no', $forwardOrder->source_order_no)
            ->where('pid', $forwardOrder->source_pid)
            ->first();

        if (! $order) {
            Log::channel('push_failure')->warning('定时回调：未找到产品订单', [
                'forward_order_id' => $forwardOrder->id,
                'order_no' => $forwardOrder->source_order_no,
            ]);
            return false;
        }

        // 准备推送数据
        $pushData = $order->toArray();
        $internalFields = ['id', 'sync_status', 'pushed_at', 'sync_error',
            'link_id', 'trace_id', 'product_id', 'business_id', 'channel_id',
            'organization_id', 'deleted_at', 'created_at', 'updated_at',
            'ext_json', 'internal_amount', 'settlement_id', 'settle_status'];
        foreach ($internalFields as $field) {
            unset($pushData[$field]);
        }

        $pushData['forward_id'] = $forwardOrder->id;
        $pushData['forward_status'] = $forwardOrder->forward_status;
        $pushData['target_order_no'] = $forwardOrder->target_order_no;
        $pushData['callback_processed_at'] = now()->toDateTimeString();

        // 调用统一推送服务
        $pushService = app(PushService::class);
        $result = $pushService->send($pushData, $callbackUrl);

        $isSuccess = $result['success'];

        $forwardOrder->update([
            'callback_status' => $isSuccess ? ForwardOrder::CALLBACK_SUCCESS : ForwardOrder::CALLBACK_FAILED,
            'callback_response' => $result['body'],
        ]);

        // 同步更新产品订单推送状态
        $order->update([
            'sync_status' => $isSuccess ? 1 : 2,
            'pushed_at' => $isSuccess ? now() : $order->pushed_at,
            'sync_error' => $isSuccess ? null : ($result['body']['msg'] ?? $result['body']['message'] ?? "HTTP {$result['http_status']}"),
        ]);

        if ($isSuccess) {
            Log::channel('push')->info('定时回调推送成功', [
                'forward_order_id' => $forwardOrder->id,
                'order_no' => $order->order_no,
                'callback_url' => $callbackUrl,
                'http_status' => $result['http_status'],
                'response' => $result['body'],
            ]);
        } else {
            Log::channel('push_failure')->warning('定时回调推送失败', [
                'forward_order_id' => $forwardOrder->id,
                'order_no' => $order->order_no,
                'callback_url' => $callbackUrl,
                'http_status' => $result['http_status'],
                'response' => $result['body'],
            ]);
        }

        return $isSuccess;
    }
}