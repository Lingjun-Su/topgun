<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Jobs\AsyncOrderAuditJob;

class AsyncOrderAuditListener
{
    /**
     * 处理订单创建事件：写入审计日志
     */
    public function handleOrderCreated(OrderCreated $event): void
    {
        $auditData = [
            'auditable_type' => get_class($event->order),
            'auditable_id' => $event->order->id,
            'event' => 'created',
            'new_values' => json_encode($event->order->getAttributes(), JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'tags' => 'env:'.($event->isTestEnv ? 'test' : 'prod')."|pid:{$event->channel->pid}",
            'created_at' => now(),
        ];

        AsyncOrderAuditJob::dispatch($auditData)->onQueue('low');
    }

    /**
     * 处理订单状态变更事件：写入审计日志 + 状态流水
     */
    public function handleOrderStatusUpdated(OrderStatusUpdated $event): void
    {
        $auditData = [
            'auditable_type' => get_class($event->order),
            'auditable_id' => $event->order->id,
            'event' => 'status_updated',
            'new_values' => json_encode($event->order->getAttributes(), JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'tags' => 'env:'.($event->isTestEnv ? 'test' : 'prod')."|pid:{$event->channel->pid}",
            'created_at' => now(),
        ];

        AsyncOrderAuditJob::dispatch($auditData, $event->statusData)->onQueue('low');
    }
}