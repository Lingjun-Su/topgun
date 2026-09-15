<?php

namespace App\Services\ThirdChannel\Suppliers;

use App\Services\ThirdChannel\Contracts\ChannelServiceInterface;

class DefaultService implements ChannelServiceInterface
{
    /**
     * 发送请求
     */
    public function send(array $data): array
    {
        return [
            'status' => 'success',
            'message' => 'Default send success',
        ];
    }

    /**
     * 查询订单
     */
    public function query(string $orderNo): array
    {
        return [
            'status' => 'success',
            'message' => 'Default query success',
        ];
    }

    /**
     * 取消订单
     */
    public function cancel(string $orderNo): array
    {
        return [
            'status' => 'success',
            'message' => 'Default cancel success',
        ];
    }
}
