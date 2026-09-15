<?php

namespace App\Events;

use App\Models\ThirdChannels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Model  $order  订单模型（ProductOrder 或 ProductOrderTest）
     * @param  ThirdChannels  $channel  来源渠道
     * @param  bool  $isTestEnv  是否为测试环境
     */
    public function __construct(
        public Model $order,
        public ThirdChannels $channel,
        public bool $isTestEnv
    ) {}
}