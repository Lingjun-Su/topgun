<?php

namespace App\Services\ThirdChannel\Contracts;

interface ChannelServiceInterface
{
    /**
     * 发送请求
     */
    public function send(array $data): array;

    /**
     * 查询订单
     */
    public function query(string $orderNo): array;

    /**
     * 取消订单
     */
    public function cancel(string $orderNo): array;
}
