<?php

namespace App\Events;

use App\Models\ThirdChannels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Model  $order  订单模型
     * @param  ThirdChannels  $channel  来源渠道
     * @param  bool  $isTestEnv  是否为测试环境
     * @param  int  $oldStatus  变更前状态
     * @param  int  $newStatus  变更后状态
     * @param  array  $statusData  状态流水数据
     */
    public function __construct(
        public Model $order,
        public ThirdChannels $channel,
        public bool $isTestEnv,
        public int $oldStatus,
        public int $newStatus,
        public array $statusData
    ) {}
}