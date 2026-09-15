<?php

namespace App\Services\ThirdChannel;

use App\Models\CallbackLog;

interface CallbackInterpreter
{
    /**
     * 解释回调数据，执行业务逻辑
     *
     * @param  CallbackLog  $log  回调日志记录
     * @param  array  $config  渠道的 callback_config 配置
     * @return array 处理结果 ['success' => bool, 'forward_order_id' => int|null, 'product_order_id' => int|null, 'error' => string|null]
     */
    public function interpret(CallbackLog $log, array $config): array;
}