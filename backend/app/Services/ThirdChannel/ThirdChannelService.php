<?php

namespace App\Services\ThirdChannel;

use App\Models\ThirdChannels;
use App\Services\ThirdChannel\ChannelConfigLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 第三方渠道管理服务
 * 封装渠道相关的业务逻辑
 */
class ThirdChannelService
{
    /**
     * 同步渠道产品配置
     * 逻辑：逻辑删除旧配置，写入新配置
     *
     * @param  int  $channelId  渠道ID
     * @param  array  $products  产品配置列表
     *
     * @throws \Exception
     */
    public function syncProducts(int $channelId, array $products): bool
    {
        $channel = ThirdChannels::findOrFail($channelId);

        return DB::transaction(function () use ($channel, $channelId, $products) {
            // 逻辑删除当前渠道关联的所有产品
            $channel->channelProducts()->delete();

            // 插入新配置
            if (! empty($products)) {
                $items = [];
                foreach ($products as $item) {
                    $items[] = [
                        'channel_id' => $channelId,
                        'product_id' => $item['product_id'],
                        'status' => $item['status'],
                        'remark' => $item['remark'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $channel->channelProducts()->insert($items);
            }

            // 清除相关缓存
            $this->clearChannelCache($channel->pid);

            return true;
        });
    }

    /**
     * 更新渠道后清除缓存
     */
    public function clearCacheAfterUpdate(ThirdChannels $channel): void
    {
        $this->clearChannelCache($channel->pid);
    }

    /**
     * 清除指定渠道的认证缓存
     */
    protected function clearChannelCache(string $pid): void
    {
        Cache::forget("channel_auth:{$pid}:test");
        Cache::forget("channel_auth:{$pid}:prod");

        // 清除配置加载器缓存
        app(ChannelConfigLoader::class)->clearCache($pid);
    }

    /**
     * 获取渠道详情（格式化响应）
     */
    public function getFormattedDetail(ThirdChannels $channel): array
    {
        $channel->load('channelProducts.products.business');

        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'pid' => $channel->pid,
            'key' => $channel->key,
            'organization_id' => $channel->organization_id,
            'method' => $channel->method,
            'role' => $channel->role,
            'remark' => $channel->remark,
            'status' => $channel->status,
            'is_ip_restricted' => $channel->is_ip_restricted,
            'ip_whitelist' => $channel->ip_whitelist,
            // 推送配置
            'push_base_url' => $channel->push_base_url,
            'push_endpoint' => $channel->push_endpoint,
            'push_timeout' => $channel->push_timeout,
            // 签名配置
            'sign_algorithm' => $channel->sign_algorithm,
            'sign_key' => $channel->sign_key,
            // 回调配置
            'callback_url' => $channel->callback_url,
            // 服务映射
            'service_class' => $channel->service_class,
            // 扩展配置
            'ext_config' => $channel->ext_config,
            // 推送成功/失败判断规则
            'push_success_rule' => $channel->push_success_rule,
            'push_fail_rule' => $channel->push_fail_rule,
            // 多步骤推送
            'push_steps' => $channel->push_steps,
            // 接收上家信息配置
            'receive_endpoint' => $channel->receive_endpoint,
            'receive_auth_mode' => $channel->receive_auth_mode,
            'auto_forward' => $channel->auto_forward,
            'forward_target_pid' => $channel->forward_target_pid,
            // 向下家推送信息配置
            'push_notify_url' => $channel->push_notify_url,
            'push_max_retries' => $channel->push_max_retries,
            'push_retry_delay' => $channel->push_retry_delay,
            // 定时回调推送
            'auto_callback_enabled' => $channel->auto_callback_enabled,
            'auto_callback_cron' => $channel->auto_callback_cron,
            'auto_callback_time_start' => $channel->auto_callback_time_start,
            'auto_callback_time_end' => $channel->auto_callback_time_end,
            // ====== 入站映射配置 ======
            'receive_mapping' => $channel->receive_mapping,
            // ====== 停止条件配置 ======
            'stop_rules' => $channel->stop_rules,
            'products' => $channel->channelProducts->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku_code' => $p->products->sku_code ?? null,
                    'bus_code' => $p->products->business->code ?? null,
                    'product_id' => $p->product_id,
                    'remark' => $p->remark,
                    'status' => $p->status,
                    'product_name' => $p->products->name ?? '未知产品',
                ];
            }),
        ];
    }
}
