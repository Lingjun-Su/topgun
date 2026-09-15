<?php

namespace App\Services\ThirdChannel;

use App\Models\ForwardOrder;
use App\Models\ForwardStop;
use App\Models\ProductOrder;
use App\Models\ThirdChannels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 停止条件求值服务
 *
 * 在转发 C → A 之前执行。遍历 C 渠道配置的启用的停止条件：
 *   - 仅评估命中当前 step 的条件
 *   - 按 cost（由简单→复杂）升序短路：任一命中即返回其提示，不再评估后续
 * 命中后写 forward_stops 留痕。
 *
 * 条件类型均为预定义，内部"子条件组合"逻辑在各自求值方法中写死（见 evaluateType）。
 */
class ForwardStopService
{
    /**
     * stop_rules 读取缓存 TTL（秒）：保证开关/配置修改能较快生效
     */
    const STOP_RULES_CACHE_TTL = 5;

    /**
     * 派生指标（如首订率）短缓存 TTL（秒），避免每请求跨表计算
     */
    const METRIC_CACHE_TTL = 15;

    /**
     * 求值入口：命中返回 ['condition_id','condition_type','message']，否则 null。
     */
    public function evaluate(ThirdChannels $channel, array $data, string $step): ?array
    {
        $stopRules = $this->getStopRules($channel);
        $conditions = $stopRules['conditions'] ?? [];
        if (empty($conditions)) {
            return null;
        }

        // 过滤：启用 且 step 命中
        $applicable = array_filter($conditions, function ($c) use ($step) {
            if (empty($c['enabled'])) {
                return false;
            }
            $steps = $c['step'] ?? ['getCode', 'submit'];
            return in_array($step, (array) $steps, true);
        });

        // 按 cost（简单→复杂）升序排序，支持短路
        usort($applicable, function ($a, $b) {
            $costA = ForwardStopRegistry::get($a['condition_type'] ?? '')['cost'] ?? 10;
            $costB = ForwardStopRegistry::get($b['condition_type'] ?? '')['cost'] ?? 10;
            return $costA <=> $costB;
        });

        foreach ($applicable as $condition) {
            $type = $condition['condition_type'] ?? null;
            if (! $type || ! ForwardStopRegistry::has($type)) {
                continue;
            }

            // 条件指定了产品：当前请求产品与之不匹配则跳过，不评估
            if (! empty($condition['product_id'])) {
                $condSku = $this->productSkuCode($channel, $condition['product_id']);
                $reqProduct = $data['sku_code'] ?? $data['product_id'] ?? null;
                if (empty($condSku) || $reqProduct === null || (string) $condSku !== (string) $reqProduct) {
                    continue;
                }
            }

            if ($this->evaluateType($type, $channel, $data, $step, $this->mergeParams($condition))) {
                $message = $condition['message'] ?: '命中停止条件，已暂停转发';
                $this->recordStop($channel, $data, $step, $condition, $message);
                return [
                    'condition_id' => $condition['id'] ?? null,
                    'condition_type' => $type,
                    'message' => $message,
                ];
            }
        }

        return null;
    }

    /**
     * 把条件实例的 product_id 并入求值参数（按产品隔离统计）。
     */
    protected function mergeParams(array $condition): array
    {
        $params = $condition['params'] ?? [];
        if (! empty($condition['product_id']) && empty($params['product_id'])) {
            $params['product_id'] = $condition['product_id'];
        }
        return $params;
    }

    /**
     * 类型分发求值。每种条件的内部"子条件组合"在此写死。
     */
    protected function evaluateType(string $type, ThirdChannels $channel, array $data, string $step, array $params): bool
    {
        switch ($type) {
            case 'request_limit':
                return $this->evalRequestLimit($channel, $data, $params);

            case 'first_order_limit':
                return $this->evalFirstOrderLimit($channel, $data, $params);

            case 'amount_limit':
                return $this->evalAmountLimit($channel, $data, $params);

            case 'first_order_rate_low':
                // 子条件组合（AND）：请求量≥min 且 首订率<阈值
                return $this->evalFirstOrderRateLow($channel, $data, $params);

            case 'operating_window':
                // 子条件：当前时间不在运行时段内
                return $this->evalOperatingWindow($params);

            case 'field_block':
                return $this->evalFieldBlock($data, $params);

            default:
                return false;
        }
    }

    /**
     * 请求量限制（count）。子条件：窗口内请求量 ≥ limit。
     */
    protected function evalRequestLimit(ThirdChannels $channel, array $data, array $params): bool
    {
        $scope = '';
        $period = $params['period'] ?? 'day';
        $limit = (int) ($params['limit'] ?? 0);
        $productId = $params['product_id'] ?? null;

        $count = $this->countForwardRequests(
            $channel, $data, $period,
            $this->productSkuCode($channel, $productId)
        );
        $hit = $limit > 0 && $count >= $limit;

        if ($hit) {
            Log::warning('停止条件命中：请求量超限', [
                'source_pid' => $channel->pid, 'scope' => $scope,
                'period' => $period, 'count' => $count, 'limit' => $limit,
            ]);
        }
        return $hit;
    }

    /**
     * 金额累计限制（sum）。子条件：窗口内订单金额累计 ≥ limit。
     */
    protected function evalAmountLimit(ThirdChannels $channel, array $data, array $params): bool
    {
        $period = $params['period'] ?? 'day';
        $limit = (float) ($params['limit'] ?? 0);
        $windowStart = $this->windowStart($period);

        $sum = (float) ProductOrder::where('pid', $channel->pid)
            ->when(! empty($params['product_id']), fn ($q) => $q->where('product_id', $params['product_id']))
            ->where('created_at', '>=', $windowStart)
            ->sum('total_amount');

        $hit = $limit > 0 && $sum >= $limit;
        if ($hit) {
            Log::warning('停止条件命中：金额累计超限', [
                'source_pid' => $channel->pid, 'period' => $period,
                'sum' => $sum, 'limit' => $limit,
            ]);
        }
        return $hit;
    }

    /**
     * 首订率过低（复合条件）。子条件（AND）：
     *   ①窗口内请求量 ≥ min_requests
     *   ②窗口内首订率 < rate_threshold
     */
    protected function evalFirstOrderRateLow(ThirdChannels $channel, array $data, array $params): bool
    {
        $period = $params['period'] ?? 'day';
        $minRequests = (int) ($params['min_requests'] ?? 0);
        $threshold = (float) ($params['rate_threshold'] ?? 0);

        $stats = $this->firstOrderStats(
            $channel, $period,
            $params['product_id'] ?? null,
            $this->productSkuCode($channel, $params['product_id'] ?? null)
        );
        $rate = $stats['total'] > 0 ? $stats['first'] / $stats['total'] : 1.0;

        $sub1 = $minRequests <= 0 || $stats['total'] >= $minRequests; // 请求量达标
        $sub2 = $rate < $threshold;                                   // 首订率过低

        $hit = $sub1 && $sub2;
        if ($hit) {
            Log::warning('停止条件命中：首订率过低', [
                'source_pid' => $channel->pid, 'period' => $period,
                'total' => $stats['total'], 'first' => $stats['first'],
                'rate' => round($rate, 4), 'rate_threshold' => $threshold,
            ]);
        }
        return $hit;
    }

    /**
     * 首订单数上限（count）。子条件：窗口内成功首订数 ≥ limit。
     */
    protected function evalFirstOrderLimit(ThirdChannels $channel, array $data, array $params): bool
    {
        $period = $params['period'] ?? 'day';
        $limit = (int) ($params['limit'] ?? 0);

        $count = (int) ProductOrder::where('pid', $channel->pid)
            ->when(! empty($params['product_id']), fn ($q) => $q->where('product_id', $params['product_id']))
            ->where('order_status', 1)
            ->where('created_at', '>=', $this->windowStart($period))
            ->count();

        $hit = $limit > 0 && $count >= $limit;
        if ($hit) {
            Log::warning('停止条件命中：首订单数超限', [
                'source_pid' => $channel->pid, 'period' => $period,
                'count' => $count, 'limit' => $limit,
            ]);
        }
        return $hit;
    }

    /**
     * 运行时段。子条件：当前时间不在 [start, end) 区间内（即运营时段外）停止。
     */
    protected function evalOperatingWindow(array $params): bool
    {
        $start = $params['start'] ?? null;
        $end = $params['end'] ?? null;
        if (! $start || ! $end) {
            return false;
        }

        $now = now()->format('H:i');
        $inside = $start <= $end
            ? ($now >= $start && $now < $end)
            : ($now >= $start || $now < $end); // 跨天时段

        $hit = ! $inside; // 时段外 → 停止
        if ($hit) {
            Log::warning('停止条件命中：运行时段外', [
                'start' => $start, 'end' => $end, 'now' => $now,
            ]);
        }
        return $hit;
    }

    /**
     * 字段值拦截。子条件：data[field] 对 value 按 operator 比较成立。
     */
    protected function evalFieldBlock(array $data, array $params): bool
    {
        $field = $params['field'] ?? null;
        $operator = $params['operator'] ?? 'eq';
        $value = $params['value'] ?? null;
        if (! $field || ! array_key_exists($field, $data)) {
            return false;
        }

        $fieldValue = $data[$field];
        $expected = $value;

        // in 用逗号分隔的列表
        if ($operator === 'in') {
            $expected = array_map('trim', explode(',', (string) $value));
        }

        $hit = $this->compareValue($fieldValue, $expected, $operator);
        if ($hit) {
            Log::warning('停止条件命中：字段值拦截', [
                'field' => $field, 'operator' => $operator, 'value' => $fieldValue,
            ]);
        }
        return $hit;
    }

    // ---- 统计辅助 ----

    /**
     * 统计窗口内放行的转发请求量。
     */
    protected function countForwardRequests(ThirdChannels $channel, array $data, string $period, $skuCode = null): int
    {
        $query = ForwardOrder::where('source_pid', $channel->pid)
            ->where('created_at', '>=', $this->windowStart($period));

        if (! empty($skuCode)) {
            $query->where('product_id', $skuCode); // forward_orders.product_id 存的是 sku_code
        }

        return (int) $query->count();
    }

    /**
     * 窗口内 请求量 与 首订数（带短缓存）。
     */
    protected function firstOrderStats(ThirdChannels $channel, string $period, $productId = null, $skuCode = null): array
    {
        $prod = $productId ?: 'all';
        $cacheKey = "stop_metric:first_order_rate:{$channel->pid}:{$period}:{$prod}";

        return Cache::remember($cacheKey, self::METRIC_CACHE_TTL, function () use ($channel, $period, $productId, $skuCode) {
            $windowStart = $this->windowStart($period);

            $total = (int) ForwardOrder::where('source_pid', $channel->pid)
                ->when(! empty($skuCode), fn ($q) => $q->where('product_id', $skuCode)) // forward_orders 用 sku_code
                ->where('created_at', '>=', $windowStart)
                ->count();

            $first = (int) ProductOrder::where('pid', $channel->pid)
                ->when(! empty($productId), fn ($q) => $q->where('product_id', $productId)) // product_orders 用主键 id
                ->where('order_status', 1)
                ->where('created_at', '>=', $windowStart)
                ->count();

            return ['total' => $total, 'first' => $first];
        });
    }

    /**
     * 渠道产品 product_id(数值) → sku_code(业务编码)，用于匹配 forward_orders.product_id。
     */
    protected function productSkuCode(ThirdChannels $channel, $productId = null): ?string
    {
        if (empty($productId)) {
            return null;
        }
        foreach ($channel->channelProducts as $cp) {
            if ((string) $cp->product_id === (string) $productId) {
                return $cp->products->sku_code ?? null;
            }
        }
        return null;
    }

    /**
     * 折算统计窗口起点（自然窗口）。
     */
    protected function windowStart(string $period): string
    {
        return match ($period) {
            'hour' => now()->startOfHour()->toDateTimeString(),
            'week' => now()->startOfWeek()->toDateTimeString(),
            default => now()->startOfDay()->toDateTimeString(), // day
        };
    }

    /**
     * 读取渠道停止配置（短缓存，保证开关即时性）。
     */
    protected function getStopRules(ThirdChannels $channel): array
    {
        $cacheKey = "channel_stop_rules:{$channel->pid}";
        $rules = Cache::get($cacheKey);
        if ($rules === null) {
            $rules = $channel->stop_rules ?: [];
            Cache::put($cacheKey, $rules, self::STOP_RULES_CACHE_TTL);
        }
        return $rules;
    }

    /**
     * 命中留痕。
     */
    protected function recordStop(ThirdChannels $channel, array $data, string $step, array $condition, string $message): void
    {
        try {
            ForwardStop::create([
                'trace_id' => $data['trace_id'] ?? null,
                'channel_id' => $channel->id,
                'source_pid' => $channel->pid,
                'source_order_no' => $data['order_no'] ?? null,
                'mobile' => $data['mobile'] ?? $data['user_phone'] ?? null,
                'step' => $step,
                'condition_id' => $condition['id'] ?? null,
                'condition_type' => $condition['condition_type'] ?? null,
                'rule_message' => $message,
                'request_data' => $data,
            ]);
        } catch (\Throwable $e) {
            // 留痕失败不应阻断主流程，仅记录
            Log::warning('停止条件命中但留痕失败', [
                'source_pid' => $channel->pid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 值比较（与 ForwardService 同款算子）。
     */
    protected function compareValue(mixed $fieldValue, mixed $expectedValue, string $operator): bool
    {
        return match ($operator) {
            'eq', '==' => $fieldValue == $expectedValue,
            '===' => $fieldValue === $expectedValue,
            'neq', '!=' => $fieldValue != $expectedValue,
            'gt' => $fieldValue > $expectedValue,
            'gte', '>=' => $fieldValue >= $expectedValue,
            'lt' => $fieldValue < $expectedValue,
            'lte', '<=' => $fieldValue <= $expectedValue,
            'in' => in_array($fieldValue, (array) $expectedValue),
            'not_in' => ! in_array($fieldValue, (array) $expectedValue),
            'contains' => is_string($fieldValue) && str_contains($fieldValue, (string) $expectedValue),
            'regex' => is_string($fieldValue) && preg_match((string) $expectedValue, $fieldValue) === 1,
            'exists' => $fieldValue !== null,
            'not_exists' => $fieldValue === null,
            default => $fieldValue == $expectedValue,
        };
    }
}
