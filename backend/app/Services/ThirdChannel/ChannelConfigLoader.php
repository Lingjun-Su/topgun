<?php

namespace App\Services\ThirdChannel;

use App\Models\ThirdChannels;
use Illuminate\Support\Facades\Cache;

/**
 * 渠道配置加载器
 * 从数据库读取渠道配置，优先使用结构化字段，回退到 parameter_name/value
 * 支持缓存，减少数据库查询
 */
class ChannelConfigLoader
{
    /**
     * 缓存 TTL（秒）
     */
    const CACHE_TTL = 3600;

    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'channel_config:';

    /**
     * 加载渠道完整配置
     *
     * @param  string  $pid  渠道PID
     * @param  int|null  $expectedStatus  期望状态（1=正式, 2=测试）
     * @return ThirdChannels|null
     */
    public function load(string $pid, ?int $expectedStatus = null): ?ThirdChannels
    {
        $cacheKey = self::CACHE_PREFIX.$pid;

        $channel = Cache::get($cacheKey);
        if ($channel) {
            if ($expectedStatus !== null && $channel->status != $expectedStatus) {
                Cache::forget($cacheKey);
                $channel = null;
            }
        }

        if (! $channel) {
            $query = ThirdChannels::where('pid', $pid);
            if ($expectedStatus !== null) {
                $query->where('status', $expectedStatus);
            }
            $channel = $query->first();

            if ($channel) {
                Cache::put($cacheKey, $channel, self::CACHE_TTL);
            }
        }

        return $channel;
    }

    /**
     * 获取推送完整 URL（base_url + endpoint）
     *
     * @param  ThirdChannels  $channel
     * @return string|null
     */
    public function getPushUrl(ThirdChannels $channel): ?string
    {
        $baseUrl = $channel->push_base_url;
        $endpoint = $channel->push_endpoint;

        if (! $baseUrl) {
            // 回退到 config/channels.php 中的配置
            $config = config("channels.{$channel->pid}");
            $baseUrl = $config['base_url'] ?? null;
        }

        if (! $baseUrl) {
            return null;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = $endpoint ? '/'.ltrim($endpoint, '/') : '';

        return $baseUrl.$endpoint;
    }

    /**
     * 获取指定步骤的推送完整 URL（base_url + step endpoint）
     *
     * @param  ThirdChannels  $channel
     * @param  array  $step  步骤配置，包含 endpoint 字段
     * @return string|null
     */
    public function getStepUrl(ThirdChannels $channel, array $step): ?string
    {
        $baseUrl = $channel->push_base_url;
        $endpoint = $step['endpoint'] ?? $channel->push_endpoint;

        if (! $baseUrl) {
            $config = config("channels.{$channel->pid}");
            $baseUrl = $config['base_url'] ?? null;
        }

        if (! $baseUrl) {
            return null;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = $endpoint ? '/'.ltrim($endpoint, '/') : '';

        return $baseUrl.$endpoint;
    }

    /**
     * 获取推送超时时间
     *
     * @param  ThirdChannels  $channel
     * @return int
     */
    public function getPushTimeout(ThirdChannels $channel): int
    {
        if ($channel->push_timeout !== null) {
            return (int) $channel->push_timeout;
        }

        // 回退到配置文件
        return config("channels.{$channel->pid}.timeout", 10);
    }

    /**
     * 获取签名算法
     *
     * @param  ThirdChannels  $channel
     * @return string
     */
    public function getSignAlgorithm(ThirdChannels $channel): string
    {
        $algorithm = $channel->sign_algorithm ?? 'sha256';

        if (! in_array($algorithm, SignService::SUPPORTED_ALGORITHMS)) {
            $algorithm = 'sha256';
        }

        return $algorithm;
    }

    /**
     * 获取签名密钥
     *
     * @param  ThirdChannels  $channel
     * @param  bool  $forPush  是否为推送签名（推送使用 sign_key，接收使用 key）
     * @return string
     */
    public function getSignKey(ThirdChannels $channel, bool $forPush = false): string
    {
        if ($forPush && $channel->sign_key) {
            return $channel->sign_key;
        }

        return $channel->key;
    }

    /**
     * 获取渠道对应的 Service 类名
     *
     * @param  ThirdChannels  $channel
     * @return string|null
     */
    public function getServiceClass(ThirdChannels $channel): ?string
    {
        if ($channel->service_class) {
            return $channel->service_class;
        }

        return null;
    }

    /**
     * 获取扩展配置
     *
     * @param  ThirdChannels  $channel
     * @return array
     */
    public function getExtConfig(ThirdChannels $channel): array
    {
        return $channel->ext_config ?? [];
    }

    /**
     * 清除指定渠道的配置缓存
     *
     * @param  string  $pid
     */
    public function clearCache(string $pid): void
    {
        Cache::forget(self::CACHE_PREFIX.$pid);
    }

    /**
     * 获取推送重试配置
     *
     * @param  ThirdChannels  $channel
     * @return array  ['max_retries' => int, 'retry_delay' => int]
     */
    public function getPushRetryConfig(ThirdChannels $channel): array
    {
        return [
            'max_retries' => (int) ($channel->push_max_retries ?? 3),
            'retry_delay' => (int) ($channel->push_retry_delay ?? 10),
        ];
    }

    /**
     * 获取推送结果通知地址（向下家通知）
     *
     * @param  ThirdChannels  $channel
     * @return string|null
     */
    public function getPushNotifyUrl(ThirdChannels $channel): ?string
    {
        return $channel->push_notify_url ?: null;
    }

    /**
     * 通过业务归属组织解析目标供应商（A）
     *
     * 单一入口：业务归属组织 → third_channels(organization_id=org_id, role=supplier_a, status=enabled)
     * 用于同步链路中 B 收到 C 的验证码请求后，根据业务找到它所属的 A 渠道，
     * 再使用该 A 的推送配置完成 getCode/submit 转发。
     *
     * @param  int  $orgId  业务归属组织ID（business.org_id）
     * @return ThirdChannels|null
     */
    public function getForwardTargetByOrg(int $orgId): ?ThirdChannels
    {
        return ThirdChannels::where('organization_id', $orgId)
            ->where('role', 'supplier_a')
            ->where('status', '<>', 0)
            ->orderBy('id')
            ->first();
    }
}