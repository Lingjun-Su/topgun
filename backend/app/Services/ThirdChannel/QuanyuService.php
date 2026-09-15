<?php

namespace App\Services\ThirdChannel;

use App\Models\QuanyuOrder;
use App\Models\ThirdChannels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuanyuService
{
    protected static ?ChannelConfigLoader $configLoader = null;

    /**
     * 获取渠道配置
     * 优先从数据库渠道配置读取，回退到 config/channels.php
     */
    protected static function getConfig(): array
    {
        // 尝试从数据库渠道配置读取
        $channel = self::getChannelModel();
        if ($channel) {
            $loader = self::getConfigLoader();
            $baseUrl = $loader->getPushUrl($channel);
            if ($baseUrl) {
                return [
                    'base_url' => $baseUrl,
                    'pid' => $channel->pid,
                    'key' => $loader->getSignKey($channel, true),
                    'bus_code' => $channel->ext_config['bus_code'] ?? config('channels.quanyu.bus_code', ''),
                    'sku_code' => $channel->ext_config['sku_code'] ?? config('channels.quanyu.sku_code', ''),
                    'timeout' => $loader->getPushTimeout($channel),
                ];
            }
        }

        // 回退到配置文件
        return config('channels.quanyu');
    }

    /**
     * 获取渠道模型实例
     */
    protected static function getChannelModel(): ?ThirdChannels
    {
        $pid = config('channels.quanyu.pid', '186');
        return ThirdChannels::where('pid', $pid)->first();
    }

    /**
     * 获取配置加载器
     */
    protected static function getConfigLoader(): ChannelConfigLoader
    {
        if (self::$configLoader === null) {
            self::$configLoader = app(ChannelConfigLoader::class);
        }
        return self::$configLoader;
    }

    /**
     * 处理订单数据（新增或更新）
     */
    public static function store($data, $organization_id)
    {
        $data['organization_id'] = $organization_id;

        return QuanyuOrder::create($data);
    }

    /**
     * 统一推送入口（Controller 调用此方法，type 为 'cancel' 时执行退订推送）
     */
    public function pushToTianxuan($order, string $type = 'push'): array
    {
        return $type === 'cancel'
            ? self::cancelPushToTianxuan($order)
            : $this->OrderPushToTianxuan($order);
    }

    /**
     * 执行推送逻辑 (可被 Controller 或 手动重试的任务复用)
     */
    public function OrderPushToTianxuan($order): array
    {
        $url = '/api_v2/ThirdChannel/syncUserData';
        $params = [
            'mobile' => $order->mobile,
            'pid' => self::getConfig()['pid'],
            'bus_code' => self::getConfig()['bus_code'],
            'sku_code' => self::getConfig()['sku_code'],
            'order_no' => $order->order_no,
            'create_time' => (string) time(),
            'type' => '1',
            'platform' => $order->platform ?? '333',
            'pack' => $order->pack ?? 'eee',
            'url' => $order->url ?? 'www.baidu.com',
            'ip' => request()->ip() ?? '127.0.0.1',
            'sms_time' => (string) time(),
            'code' => $order->code ?? '123456',
        ];

        $sign = self::generateSign($params);

        try {
            $response = Http::withHeaders(['sign' => $sign])
                ->timeout(self::getConfig()['timeout'])
                ->post(self::getConfig()['base_url'].$url, $params);

            $resData = $response->json();
            if ($response->successful() && isset($resData['code']) && $resData['code'] === 0) {
                $order->update([
                    'sync_status' => 1,
                    'pushed_at' => now(),
                    'sync_error' => null,
                ]);

                return ['code' => 0, 'msg' => '同步成功', 'url' => $url, 'push' => $resData];
            } else {
                $errorMsg = $resData['msg'] ?? '接口返回错误';
                $this->markAsFailed($order, "Code: {$resData['code']}, Msg: {$errorMsg}");

                return ['code' => 403, 'msg' => $errorMsg, 'params' => $params, 'return' => $resData, 'url' => $url];
            }
        } catch (\Exception $e) {
            $this->markAsFailed($order, '网络异常: '.$e->getMessage());

            return ['code' => 405, 'msg' => '网络异常', 'error' => $e->getMessage(), 'url' => $url];
        }
    }

    /**
     * 执行退订推送逻辑
     */
    public static function cancelPushToTianxuan($order): array
    {
        $url = '/api_v2/ThirdChannel/syncUnsubUserData';
        $params = [
            'mobile' => $order->mobile,
            'pid' => self::getConfig()['pid'],
            'bus_code' => self::getConfig()['bus_code'],
            'order_no' => $order->order_no,
            'create_time' => (string) time(),
        ];

        $sign = self::generateSign($params);

        try {
            $response = Http::withHeaders(['sign' => $sign])
                ->timeout(self::getConfig()['timeout'])
                ->post(self::getConfig()['base_url'].$url, $params);

            $resData = $response->json();
            if ($response->successful() && isset($resData['code']) && $resData['code'] === 0) {
                $order->update([
                    'cancel_sync_status' => 1,
                    'cancel_at' => now(),
                    'sync_error' => null,
                ]);

                return ['code' => 0, 'msg' => '同步成功', 'url' => $url];
            } else {
                $errorMsg = $resData['msg'] ?? '接口返回错误';
                self::cancelMarkAsFailed($order, "Code: {$resData['code']}, Msg: {$errorMsg}");

                return ['code' => 403, 'msg' => $errorMsg, 'params' => $params, 'return' => $resData, 'url' => $url];
            }
        } catch (\Exception $e) {
            self::cancelMarkAsFailed($order, '网络异常: '.$e->getMessage());

            return ['code' => 405, 'msg' => '网络异常', 'error' => $e->getMessage(), 'url' => $url];
        }
    }

    /**
     * 严谨的签名算法实现
     * 1. 字典序排序 2. 格式化拼接 3. 拼Key 4. MD5 5. 大写
     */
    protected static function generateSign(array $params): string
    {
        ksort($params);

        $string = '';
        foreach ($params as $key => $val) {
            $string .= "{$key}={$val}&";
        }

        $string .= 'key='.self::getConfig()['key'];

        return strtoupper(md5($string));
    }

    protected function markAsFailed($order, string $reason): void
    {
        $order->update([
            'sync_status' => 2,
            'sync_error' => mb_substr($reason, 0, 500),
        ]);
        Log::warning("下家同步失败 [ID: {$order->id}]: ".$reason);
    }

    protected static function cancelMarkAsFailed($order, string $reason): void
    {
        $order->update([
            'cancel_sync_status' => 2,
            'cancel_sync_error' => mb_substr($reason, 0, 500),
        ]);
        Log::warning("下家同步失败 [ID: {$order->id}]: ".$reason);
    }
}
