<?php

namespace App\Services;

use App\Models\BusinessRule;
use App\Models\ThirdChannels;
use Illuminate\Support\Facades\Log;

/**
 * 业务规则引擎
 * 根据渠道、产品、业务等维度匹配规则，执行规则动作
 */
class RuleEngine
{
    /**
     * 对订单数据执行规则匹配
     *
     * @param ThirdChannels $channel 渠道
     * @param array $orderData 订单数据
     * @return array|null 匹配规则的动作，无匹配返回null
     */
    public function execute(ThirdChannels $channel, array $orderData): ?array
    {
        // 获取所有启用的规则，按优先级排序
        $rules = BusinessRule::where('status', BusinessRule::STATUS_ENABLED)
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        foreach ($rules as $rule) {
            if ($this->matches($rule, $channel, $orderData)) {
                $actions = $rule->actions;
                $this->logRuleMatch($rule, $orderData);
                return $actions;
            }
        }

        return null;
    }

    /**
     * 判断规则是否匹配
     */
    protected function matches(BusinessRule $rule, ThirdChannels $channel, array $orderData): bool
    {
        // 1. 渠道匹配
        if ($rule->channel_id !== null && $rule->channel_id != $channel->id) {
            return false;
        }

        // 2. 业务匹配
        if ($rule->business_id !== null) {
            $orderBusinessId = $orderData['business_id'] ?? null;
            if ($orderBusinessId != $rule->business_id) {
                return false;
            }
        }

        // 3. 产品匹配
        if ($rule->product_id !== null) {
            $orderProductId = $orderData['product_id'] ?? null;
            if ($orderProductId != $rule->product_id) {
                return false;
            }
        }

        // 4. 条件表达式匹配
        $conditions = $rule->conditions;
        if (! empty($conditions)) {
            return $this->evaluateConditions($conditions, $orderData);
        }

        return true;
    }

    /**
     * 评估条件表达式
     */
    protected function evaluateConditions(array $conditions, array $orderData): bool
    {
        $operator = $conditions['operator'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $condition) {
            $field = $condition['field'] ?? '';
            $op = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';
            $actualValue = $orderData[$field] ?? null;

            $result = $this->compare($actualValue, $op, $value);

            if ($operator === 'AND' && ! $result) {
                return false;
            }
            if ($operator === 'OR' && $result) {
                return true;
            }
        }

        return $operator === 'AND';
    }

    /**
     * 比较两个值
     */
    protected function compare($actual, string $operator, $expected): bool
    {
        return match ($operator) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'in' => is_array($expected) ? in_array($actual, $expected) : false,
            'not_in' => is_array($expected) ? ! in_array($actual, $expected) : true,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'starts_with' => is_string($actual) && str_starts_with($actual, (string) $expected),
            'ends_with' => is_string($actual) && str_ends_with($actual, (string) $expected),
            'empty' => empty($actual),
            'not_empty' => ! empty($actual),
            default => false,
        };
    }

    /**
     * 记录规则匹配日志
     */
    protected function logRuleMatch(BusinessRule $rule, array $orderData): void
    {
        Log::channel('push')->info('业务规则匹配', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'order_no' => $orderData['order_no'] ?? 'N/A',
            'actions' => $rule->actions,
        ]);
    }
}