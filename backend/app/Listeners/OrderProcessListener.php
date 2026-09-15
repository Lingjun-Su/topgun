<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\PushToXinquanyu;
use App\Models\QuanyuOrder;
use App\Services\RuleEngine;
use App\Services\ThirdChannel\ChannelFactory;
use App\Services\ThirdChannel\ChannelConfigLoader;
use Illuminate\Support\Facades\Log;

class OrderProcessListener
{
    /**
     * 处理订单创建事件：根据渠道配置执行数据转换和推送
     */
    public function handle(OrderCreated $event): void
    {
        $channel = $event->channel;

        // 判断渠道是否需要推送（method 包含 send/forward/发送/转发）
    if (! str_contains($channel->method, 'send') && ! str_contains($channel->method, 'forward')
        && ! str_contains($channel->method, '发送') && ! str_contains($channel->method, '转发')) {
        return;
    }

        $data = $event->order->toArray();

        // 推送条件过滤：根据渠道配置的条件判断是否应该推送
        if (! $this->shouldPush($channel, $data)) {
            Log::info('订单推送被条件过滤拦截', [
                'order_no' => $data['order_no'] ?? 'N/A',
                'pid' => $channel->pid,
                'channel_id' => $channel->id,
            ]);
            return;
        }

        // 业务规则引擎：执行规则匹配，规则可修改推送行为
        $ruleEngine = app(RuleEngine::class);
        $ruleActions = $ruleEngine->execute($channel, $data);
        if ($ruleActions === null) {
            // 没有匹配规则，按默认逻辑推送
            $this->dispatchPush($channel, $data, $event->order);
        } elseif (isset($ruleActions['action']) && $ruleActions['action'] === 'skip') {
            // 规则明确要求跳过推送
            Log::info('订单推送被业务规则跳过', [
                'order_no' => $data['order_no'] ?? 'N/A',
                'rule' => $ruleActions['rule_name'] ?? 'unknown',
            ]);
            return;
        } else {
            // 规则指定了自定义动作
            $this->dispatchWithRuleActions($channel, $data, $event->order, $ruleActions);
        }
    }

    /**
     * 推送条件过滤
     * 根据渠道配置的推送条件，判断当前订单是否应该推送
     *
     * 支持的配置（存储在 ext_config.push_conditions 中）：
     * - time_window: 时间窗口限制，如只允许在 09:00-22:00 推送
     * - order_types: 允许推送的订单类型
     * - order_statuses: 允许推送的订单状态
     *
     * @param  \App\Models\ThirdChannels  $channel
     * @param  array  $data
     * @return bool
     */
    protected function shouldPush($channel, array $data): bool
    {
        $extConfig = $channel->ext_config ?? [];
        $conditions = $extConfig['push_conditions'] ?? [];

        // 未配置任何条件，默认允许推送
        if (empty($conditions)) {
            return true;
        }

        // 1. 时间窗口过滤
        if (isset($conditions['time_window']['enabled']) && $conditions['time_window']['enabled']) {
            $start = $conditions['time_window']['start'] ?? '00:00';
            $end = $conditions['time_window']['end'] ?? '23:59';
            $now = now()->format('H:i');
            if ($now < $start || $now > $end) {
                Log::info('推送条件过滤：时间窗口不匹配', [
                    'now' => $now,
                    'window_start' => $start,
                    'window_end' => $end,
                ]);
                return false;
            }
        }

        // 2. 订单类型过滤
        if (isset($conditions['order_types']['enabled']) && $conditions['order_types']['enabled']) {
            $allowedTypes = $conditions['order_types']['allowed'] ?? [];
            $orderType = $data['type'] ?? '';
            if (! empty($allowedTypes) && ! in_array((string) $orderType, $allowedTypes)) {
                Log::info('推送条件过滤：订单类型不匹配', [
                    'order_type' => $orderType,
                    'allowed_types' => $allowedTypes,
                ]);
                return false;
            }
        }

        // 3. 订单状态过滤
        if (isset($conditions['order_statuses']['enabled']) && $conditions['order_statuses']['enabled']) {
            $allowedStatuses = $conditions['order_statuses']['allowed'] ?? [];
            $orderStatus = $data['order_status'] ?? null;
            if (! empty($allowedStatuses) && $orderStatus !== null && ! in_array((int) $orderStatus, $allowedStatuses)) {
                Log::info('推送条件过滤：订单状态不匹配', [
                    'order_status' => $orderStatus,
                    'allowed_statuses' => $allowedStatuses,
                ]);
                return false;
            }
        }

        // 4. 自定义条件（通过回调函数或表达式）
        if (isset($conditions['custom']['enabled']) && $conditions['custom']['enabled']) {
            $expression = $conditions['custom']['expression'] ?? '';
            if ($expression) {
                $result = $this->evaluateCustomCondition($expression, $channel, $data);
                if (! $result) {
                    Log::info('推送条件过滤：自定义条件不满足', [
                        'expression' => $expression,
                    ]);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 评估自定义推送条件
     * 支持简单的条件表达式，如：amount > 0 && status != 2
     *
     * @param  string  $expression
     * @param  \App\Models\ThirdChannels  $channel
     * @param  array  $data
     * @return bool
     */
    protected function evaluateCustomCondition(string $expression, $channel, array $data): bool
    {
        try {
            // 提取变量
            $amount = $data['total_amount'] ?? 0;
            $quantity = $data['quantity'] ?? 0;
            $status = $data['order_status'] ?? 0;
            $type = $data['type'] ?? '';

            // 简单的表达式评估（仅支持基本比较）
            // 示例: "amount > 0 && status != 2"
            $expression = str_replace('amount', '$amount', $expression);
            $expression = str_replace('quantity', '$quantity', $expression);
            $expression = str_replace('status', '$status', $expression);
            $expression = str_replace('type', '$type', $expression);

            return eval("return {$expression};");
        } catch (\Throwable $e) {
            Log::warning('自定义推送条件评估失败', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);
            return true; // 评估失败时默认允许推送
        }
    }

    /**
     * 根据渠道配置分发推送任务
     * 支持单目标推送和多目标推送
     *
     * @param  \App\Models\ThirdChannels  $channel
     * @param  array  $data
     * @param  mixed  $order
     */
    protected function dispatchPush($channel, array $data, $order): void
    {
        // 检查是否配置了多目标推送
        $extConfig = $channel->ext_config ?? [];
        $multiTargets = $extConfig['multi_targets'] ?? [];

        if (! empty($multiTargets)) {
            $this->dispatchMultiTarget($channel, $data, $order);
            return;
        }

        try {
            // 检查是否配置了多步骤推送
            $steps = $channel->push_steps ?? [];

            if (! empty($steps) && is_array($steps)) {
                // 配置了多步骤推送：走 quanyu_orders 中转 + PushToXinquanyu（多步骤模式）
                $this->dispatchQuanyuPush($channel, $data);
            } elseif ($channel->pid === '186') {
                // 鑫全域渠道（pid=186）：走 quanyu_orders 中转 + PushToXinquanyu
                $this->dispatchQuanyuPush($channel, $data);
            } else {
                // 通用渠道：尝试通过 ChannelFactory 获取对应的 Service
                $factory = app(ChannelFactory::class);
                $service = $factory->make($channel->pid);
                $service->send($data);
            }
        } catch (\Exception $e) {
            Log::error('推送分发失败', [
                'channel_id' => $channel->id,
                'pid' => $channel->pid,
                'order_no' => $data['order_no'] ?? 'N/A',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 分发鑫全域渠道推送
     */
    protected function dispatchQuanyuPush($channel, array $data): void
    {
        // 写入 quanyu_orders 表（中转记录）
        $quanyuOrder = QuanyuOrder::create([
            'mobile' => $data['user_phone'] ?? '',
            'pid' => $channel->pid,
            'bus_code' => $data['bus_code'] ?? '',
            'sku_code' => $data['sku_code'] ?? '',
            'order_no' => $data['order_no'] ?? '',
            'create_time' => $data['order_time'] ?? now(),
            'type' => $data['type'] ?? '1',
            'platform' => $data['platform'] ?? '',
            'pack' => $data['pack'] ?? '',
            'url' => $data['url'] ?? '',
            'ip' => $data['ip'] ?? '',
            'sms_time' => $data['sms_time'] ?? '',
            'code' => $data['code'] ?? '',
            'order_status' => $data['order_status'] ?? 0,
            'price' => $data['price'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'quantity' => $data['quantity'] ?? 1,
            'sync_status' => 0,
            'organization_id' => $channel->organization_id,
        ]);

        // 异步推送至鑫全域（传递渠道PID，支持从数据库读取推送地址）
        // 端点地址优先从渠道配置的 push_endpoint 字段读取，未配置时回退到默认值
        $endpoint = $channel->push_endpoint ?? '/api_v2/ThirdChannel/syncUserData';
        PushToXinquanyu::dispatch($quanyuOrder->toArray(), $endpoint, $channel->pid);

        Log::info('订单已自动派发推送任务', [
            'order_no' => $data['order_no'] ?? 'N/A',
            'pid' => $channel->pid,
            'quanyu_order_id' => $quanyuOrder->id,
        ]);
    }

    /**
     * 根据业务规则动作执行推送
     * 支持规则指定的目标、数据修改等自定义行为
     */
    protected function dispatchWithRuleActions($channel, array $data, $order, array $actions): void
    {
        $action = $actions['action'] ?? 'push';
        $targetPid = $actions['target_pid'] ?? null;
        $modifiedData = $data;

        // 规则可以修改推送数据
        if (isset($actions['data_overrides']) && is_array($actions['data_overrides'])) {
            $modifiedData = array_merge($modifiedData, $actions['data_overrides']);
        }

        if ($action === 'forward' && $targetPid) {
            // 规则指定转发到特定渠道
            $forwardOrder = \App\Models\ForwardOrder::create([
                'source_channel_id' => $channel->id,
                'source_order_no' => $data['order_no'] ?? '',
                'source_pid' => $channel->pid,
                'target_pid' => $targetPid,
                'source_data' => $modifiedData,
                'forward_status' => \App\Models\ForwardOrder::STATUS_PENDING,
                'mobile' => $modifiedData['user_phone'] ?? $modifiedData['mobile'] ?? null,
                'organization_id' => $channel->organization_id,
            ]);
            \App\Jobs\ForwardOrderJob::dispatch($forwardOrder->id);
        } else {
            // 默认按规则推送
            $this->dispatchPush($channel, $modifiedData, $order);
        }
    }

    /**
     * 多目标推送：根据渠道配置的多个推送目标并行派发
     * 目标配置存储在 ext_config.multi_targets 中
     */
    protected function dispatchMultiTarget($channel, array $data, $order): void
    {
        $extConfig = $channel->ext_config ?? [];
        $targets = $extConfig['multi_targets'] ?? [];

        if (empty($targets)) {
            // 无多目标配置，走单目标推送
            $this->dispatchPush($channel, $data, $order);
            return;
        }

        foreach ($targets as $target) {
            $targetPid = $target['pid'] ?? null;
            $targetMethod = $target['method'] ?? 'send';

            try {
                if ($targetPid === '186' || $targetMethod === 'quanyu') {
                    // 鑫全域推送
                    $this->dispatchQuanyuPush($channel, $data);
                } elseif ($targetMethod === 'forward' && $targetPid) {
                    // 数据中转
                    $forwardOrder = \App\Models\ForwardOrder::create([
                        'source_channel_id' => $channel->id,
                        'source_order_no' => $data['order_no'] ?? '',
                        'source_pid' => $channel->pid,
                        'target_pid' => $targetPid,
                        'source_data' => $data,
                        'forward_status' => \App\Models\ForwardOrder::STATUS_PENDING,
                        'mobile' => $data['user_phone'] ?? $data['mobile'] ?? null,
                        'organization_id' => $channel->organization_id,
                    ]);
                    \App\Jobs\ForwardOrderJob::dispatch($forwardOrder->id);
                } else {
                    // 通用渠道推送
                    $factory = app(ChannelFactory::class);
                    $service = $factory->make($targetPid ?? $channel->pid);
                    $service->send($data);
                }

                Log::info('多目标推送已派发', [
                    'order_no' => $data['order_no'] ?? 'N/A',
                    'source_pid' => $channel->pid,
                    'target_pid' => $targetPid ?? 'default',
                ]);
            } catch (\Exception $e) {
                Log::error('多目标推送分发失败', [
                    'channel_id' => $channel->id,
                    'target_pid' => $targetPid,
                    'order_no' => $data['order_no'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}