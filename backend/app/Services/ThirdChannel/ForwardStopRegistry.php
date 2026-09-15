<?php

namespace App\Services\ThirdChannel;

/**
 * 停止条件类型注册表
 *
 * 每个"条件类型"是开发者预定义好的：内部组合逻辑（子条件）在求值器中写死，
 * 用户只在编辑器里按 schema 填值。新增一个条件类型 = 在此注册一条描述 +
 * 在 ForwardStopService::evaluateType 增加一个分支（见各求值方法的注释）。
 *
 * schema 字段供前端渲染该类型的填值表单使用：
 *   key      表单字段名（写入 params）
 *   label    字段提示
 *   type     输入控件：number | text | time | select | switch
 *   options  type=select 时的选项 [{label,value}]
 *   hint     可选说明
 */
class ForwardStopRegistry
{
    /**
     * 返回全部条件类型描述。
     *
     * @return array<string, array>  key => ['label','cost','schema',...]
     */
    public static function all(): array
    {
        return [
            // 阈值/限制类：count
            'request_limit' => [
                'label' => '请求量上限',
                'cost'  => 2,
                'desc'  => '统计周期内验证码请求量达到上限即拦截',
                'schema' => [
                    ['key' => 'period', 'label' => '统计周期', 'type' => 'select', 'options' => [
                        ['label' => '按天', 'value' => 'day'],
                        ['label' => '按小时', 'value' => 'hour'],
                        ['label' => '按周', 'value' => 'week'],
                    ], 'default' => 'day'],
                    ['key' => 'limit',  'label' => '上限值', 'type' => 'number', 'default' => 1000],
                ],
            ],

            // 条件2：首订单数上限（窗口内成功首订达到 limit 即停）
            'first_order_limit' => [
                'label' => '首订单数上限',
                'cost'  => 2,
                'desc'  => '统计周期内成功首订数达到上限即拦截',
                'schema' => [
                    ['key' => 'period', 'label' => '统计周期', 'type' => 'select', 'options' => [
                        ['label' => '天', 'value' => 'day'],
                    ], 'default' => 'day'],
                    ['key' => 'limit', 'label' => '首订成功数上限', 'type' => 'number', 'default' => 500],
                ],
            ],

            // 阈值/限制类：sum（金额累计）
            'amount_limit' => [
                'label' => '金额累计上限',
                'cost'  => 2,
                'desc'  => '统计周期内累计金额达到上限即拦截',
                'schema' => [
                    ['key' => 'period', 'label' => '统计周期', 'type' => 'select', 'options' => [
                        ['label' => '小时', 'value' => 'hour'],
                        ['label' => '天', 'value' => 'day'],
                    ], 'default' => 'day'],
                    ['key' => 'limit',  'label' => '金额上限', 'type' => 'number', 'default' => 50000],
                ],
            ],

            // 条件3：成功率过低（请求量≥min 且 首订成功率<率）
            'first_order_rate_low' => [
                'label' => '成功率过低',
                'cost'  => 3,
                'desc'  => '请求量≥最低请求量且首订成功率低于阈值时拦截',
                'schema' => [
                    ['key' => 'period',        'label' => '统计周期', 'type' => 'select',
                        'options' => [['label' => '天', 'value' => 'day']], 'default' => 'day'],
                    ['key' => 'min_requests',  'label' => '最低请求量', 'type' => 'number',
                        'hint' => '否则请求量不足时先不判', 'default' => 100],
                    ['key' => 'rate_threshold', 'label' => '成功率阈值(0-1)',
                        'type' => 'number', 'hint' => '首订成功/请求量，如 0.10 表示 10%', 'default' => 0.10],
                ],
            ],

            // 条件1：运行时段（当前时间不在该区间内即停止）
            'operating_window' => [
                'label' => '运行时段',
                'cost'  => 1,
                'desc'  => '当前时间不在运行时段内即拦截',
                'schema' => [
                    ['key' => 'start', 'label' => '开始时间', 'type' => 'time',
                        'hint' => '此区间外停止转发', 'default' => '07:00'],
                    ['key' => 'end',   'label' => '结束时间', 'type' => 'time', 'default' => '22:00'],
                ],
            ],

            // 字段规则类
            'field_block' => [
                'label' => '字段值拦截',
                'cost'  => 0,
                'desc'  => '请求字段值命中所设运算符/值时拦截',
                'schema' => [
                    ['key' => 'field',    'label' => '字段', 'type' => 'text',
                        'hint' => '请求字段名，如 mobile / amount', 'default' => 'mobile'],
                    ['key' => 'operator', 'label' => '运算符', 'type' => 'select', 'options' => [
                        ['label' => '等于(eq)', 'value' => 'eq'],
                        ['label' => '不等于(neq)', 'value' => 'neq'],
                        ['label' => '大于(gt)', 'value' => 'gt'],
                        ['label' => '大于等于(gte)', 'value' => 'gte'],
                        ['label' => '小于(lt)', 'value' => 'lt'],
                        ['label' => '小于等于(lte)', 'value' => 'lte'],
                        ['label' => '在列表中(in)', 'value' => 'in'],
                        ['label' => '正则匹配(regex)', 'value' => 'regex'],
                        ['label' => '包含(contains)', 'value' => 'contains'],
                    ], 'default' => 'in'],
                    ['key' => 'value', 'label' => '值', 'type' => 'text',
                        'hint' => 'in 用逗号分隔列表；regex 填正则；其余填单个值', 'default' => ''],
                ],
            ],
        ];
    }

    /**
     * 条件类型是否存在
     */
    public static function has(string $type): bool
    {
        return array_key_exists($type, static::all());
    }

    /**
     * 取单个类型描述
     */
    public static function get(string $type): ?array
    {
        return static::all()[$type] ?? null;
    }
}
