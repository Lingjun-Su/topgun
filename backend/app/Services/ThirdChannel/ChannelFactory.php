<?php

// app/Services/ThirdChannel/ChannelFactory.php

namespace App\Services\ThirdChannel;

use App\Models\ThirdChannels;
use App\Services\ThirdChannel\Contracts\ChannelServiceInterface;
use App\Services\ThirdChannel\Suppliers\SupplierAService;
use Exception;

class ChannelFactory
{
    /**
     * 渠道映射配置（静态映射，作为 service_class 字段的 fallback）
     * Key 为数据库中的 pid, Value 为对应的 Service 类名
     */
    protected static array $mapping = [
        'supplier_a' => SupplierAService::class,
        // 'supplier_b' => SupplierBService::class,
    ];

    protected ChannelConfigLoader $configLoader;

    public function __construct(ChannelConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * 创建对应的 Service 实例
     * 优先从数据库渠道配置的 service_class 字段读取，回退到静态映射
     *
     * @throws Exception
     */
    public function make(string $pid): ChannelServiceInterface
    {
        // 1. 尝试从数据库读取 service_class
        $channel = $this->configLoader->load($pid);
        if ($channel && $channel->service_class) {
            $className = $channel->service_class;
            if (class_exists($className)) {
                return app($className);
            }
        }

        // 2. 回退到静态映射
        if (isset(self::$mapping[$pid])) {
            $className = self::$mapping[$pid];
            return app($className);
        }

        // 3. 全部未找到，抛出异常
        throw new Exception("Unsupported channel: {$pid}");
    }
}
