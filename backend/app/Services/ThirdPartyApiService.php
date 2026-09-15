<?php

namespace App\Services;

use App\Models\ThirdChannels;
use Illuminate\Support\Facades\Log;

/**
 * 第三方 API 通用服务
 * 提供签名生成、请求发送等通用功能
 */
class ThirdPartyApiService
{
    /**
     * 生成签名（字典序排序 + key 拼接 + MD5 大写）
     *
     * @param  array  $params  请求参数
     * @param  string  $key  签名密钥
     * @return string  大写 MD5 签名
     */
    public static function generateSign(array $params, string $key): string
    {
        ksort($params);

        $string = '';
        foreach ($params as $k => $v) {
            $string .= "{$k}={$v}&";
        }

        $string .= "key={$key}";

        return strtoupper(md5($string));
    }

    /**
     * 从渠道配置获取签名密钥
     * 优先使用渠道的 sign_key，回退到 key
     *
     * @param  string  $pid  渠道 PID
     * @return string|null
     */
    public static function getKeyForChannel(string $pid): ?string
    {
        try {
            $channel = ThirdChannels::where('pid', $pid)->first();
            if ($channel) {
                return $channel->sign_key ?? $channel->key;
            }
        } catch (\Exception $e) {
            Log::warning('读取渠道签名密钥失败', [
                'pid' => $pid,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}