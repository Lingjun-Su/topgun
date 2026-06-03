<?php
// app/Services/ThirdChannel/ChannelFactory.php
namespace App\Services\ThirdChannel;

use App\Services\ThirdChannel\Contracts\ChannelServiceInterface;
use App\Services\ThirdChannel\Suppliers\SupplierAService;
use App\Services\ThirdChannel\Suppliers\DefaultService;
use Exception;

class ChannelFactory
{
    /**
     * 渠道映射配置
     * Key 为数据库中的 pid, Value 为对应的 Service 类名
     */
    protected static array $mapping = [
        'supplier_a' => SupplierAService::class,
        // 'supplier_b' => SupplierBService::class,
    ];

    /**
     * 创建对应的 Service 实例
     * @throws Exception
     */
    public static function make(string $pid): ChannelServiceInterface
    {
        if (!isset(self::$mapping[$pid])) {
            // 如果没有特定逻辑，可以返回一个通用 Service 或抛出异常
            throw new Exception("Unsupported channel: {$pid}");
        }

        $className = self::$mapping[$pid];

        // 利用 Laravel 容器自动注入（如果 Service 构造函数有依赖）
        return app($className);
    }
}
