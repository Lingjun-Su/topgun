<?php

namespace App\Jobs;

use App\Models\ProductOrder;
use App\Models\ProductOrderTest;
use App\Models\ThirdChannels;
use App\Notifications\PushFailureNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 失败告警任务
 * 定时扫描 sync_status=2 的失败记录，发送通知给管理员
 */
class AlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * 每次扫描的最大失败记录数
     */
    const MAX_FAILURES = 50;

    /**
     * 最近一次告警的时间缓存键
     */
    const LAST_ALERT_KEY = 'alert_push_failure_last_time';

    protected bool $processTestOrders;

    public function __construct(bool $processTestOrders = false)
    {
        $this->processTestOrders = $processTestOrders;
    }

    /**
     * 执行告警扫描
     */
    public function handle(): void
    {
        $modelClass = $this->processTestOrders ? ProductOrderTest::class : ProductOrder::class;
        $env = $this->processTestOrders ? 'test' : 'prod';

        // 1. 查询失败的订单（sync_status=2）
        $failures = $modelClass::where('sync_status', 2)
            ->whereNotNull('sync_error')
            ->where('sync_error', '!=', '')
            ->orderBy('updated_at', 'desc')
            ->limit(self::MAX_FAILURES)
            ->get();

        if ($failures->isEmpty()) {
            return;
        }

        Log::info("告警扫描：发现 {$failures->count()} 条失败记录（{$env}）");

        // 2. 按渠道分组，统计失败数
        $grouped = $failures->groupBy('pid');

        foreach ($grouped as $pid => $orders) {
            $channel = ThirdChannels::where('pid', $pid)->first();
            $channelName = $channel->name ?? "未知渠道({$pid})";

            $failureDetails = [
                'channel_name' => $channelName,
                'channel_pid' => $pid,
                'total_failures' => $orders->count(),
                'failed_at' => now()->toDateTimeString(),
                'order_nos' => $orders->pluck('order_no')->toArray(),
                'sample_error' => $orders->first()->sync_error,
                'env' => $env,
                'type' => 'push_failure_alert',
            ];

            // 3. 记录告警日志
            Log::warning('推送失败告警', $failureDetails);

            // 4. 发送通知给管理员（异步）
            $this->notifyAdmins($failureDetails);
        }
    }

    /**
     * 通知所有管理员用户
     */
    protected function notifyAdmins(array $failureDetails): void
    {
        try {
            // 查询启用了通知的管理员用户
            // 优先使用 role_id 关联查询，回退到通知所有用户
            $adminUsers = \App\Models\User::whereNotNull('role_id')->get();

            if ($adminUsers->isEmpty()) {
                $adminUsers = \App\Models\User::all();
            }

            foreach ($adminUsers as $admin) {
                $admin->notify(new PushFailureNotification($failureDetails));
            }
        } catch (\Exception $e) {
            Log::error('告警通知发送失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('告警扫描任务异常', [
            'error' => $exception->getMessage(),
        ]);
    }
}