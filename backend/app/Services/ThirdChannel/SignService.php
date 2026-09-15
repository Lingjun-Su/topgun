<?php

namespace App\Services\ThirdChannel;

use App\Models\ThirdChannels;

/**
 * 签名服务
 * 支持多种签名算法：sha256, md5, hmac_sha256
 * 接收层和推送层共用
 */
class SignService
{
    /**
     * 支持的签名算法列表
     */
    const SUPPORTED_ALGORITHMS = ['sha256', 'md5', 'hmac_sha256'];

    /**
     * 生成签名
     *
     * @param  array  $params  请求参数
     * @param  string  $key  签名密钥
     * @param  string  $algorithm  签名算法: sha256/md5/hmac_sha256
     * @return string
     */
    public function sign(array $params, string $key, string $algorithm = 'sha256'): string
    {
        $algorithm = strtolower($algorithm);

        return match ($algorithm) {
            'md5' => $this->md5Sign($params, $key),
            'hmac_sha256' => $this->hmacSha256Sign($params, $key),
            default => $this->sha256Sign($params, $key),
        };
    }

    /**
     * 验证签名（接收层使用：从 Header 获取签名参数）
     *
     * @param  string  $signature  请求头中的签名
     * @param  string  $rawContent  原始请求体
     * @param  string  $key  签名密钥
     * @param  string  $timestamp  时间戳
     * @param  string  $nonce  随机数
     * @param  string  $algorithm  签名算法
     * @return bool
     */
    public function verify(string $signature, string $rawContent, string $key, string $timestamp, string $nonce, string $algorithm = 'sha256'): bool
    {
        $algorithm = strtolower($algorithm);

        $computed = match ($algorithm) {
            'md5' => hash('md5', $key.$timestamp.$nonce.$rawContent),
            'hmac_sha256' => hash_hmac('sha256', $timestamp.$nonce.$rawContent, $key),
            default => hash('sha256', $key.$timestamp.$nonce.$rawContent),
        };

        return hash_equals($computed, strtolower($signature));
    }

    /**
     * 从渠道模型获取签名算法和密钥
     *
     * @param  ThirdChannels  $channel  渠道模型
     * @return array{algorithm: string, key: string}
     */
    public function getConfigForChannel(ThirdChannels $channel): array
    {
        $algorithm = $channel->sign_algorithm ?? 'sha256';
        if (! in_array($algorithm, self::SUPPORTED_ALGORITHMS)) {
            $algorithm = 'sha256';
        }

        // 推送签名密钥优先使用 sign_key，回退到 key
        $key = $channel->sign_key ?? $channel->key;

        return ['algorithm' => $algorithm, 'key' => $key];
    }

    /**
     * SHA256 签名（字典序排序 + key 拼接）
     */
    protected function sha256Sign(array $params, string $key): string
    {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            $string .= "{$k}={$v}&";
        }
        $string .= "key={$key}";

        return strtolower(hash('sha256', $string));
    }

    /**
     * MD5 签名（字典序排序 + key 拼接）
     */
    protected function md5Sign(array $params, string $key): string
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
     * HMAC-SHA256 签名
     */
    protected function hmacSha256Sign(array $params, string $key): string
    {
        ksort($params);
        $string = http_build_query($params, '', '&');

        return strtolower(hash_hmac('sha256', $string, $key));
    }
}