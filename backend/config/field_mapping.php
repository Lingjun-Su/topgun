<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 默认字段映射规则
    |--------------------------------------------------------------------------
    |
    | 定义外部系统传入字段与系统内部字段的映射关系。
    | 每个渠道可配置独立规则，未配置的渠道使用默认规则。
    |
    | 规则结构：
    |   '内部字段名' => [
    |       'field'   => '外部字段名',        // 默认与内部字段名相同
    |       'type'    => 'int|float|string|bool|datetime|json|array',
    |       'default' => '默认值',            // 外部未传时使用
    |       'format'  => 'Y-m-d H:i:s',      // type=datetime 时的输入格式
    |       'value_map' => [                  // 值映射转换
    |           '外部值' => '内部值',
    |       ],
    |   ],
    |
    */

    'default' => [
        'order_no' => [
            'field' => 'order_no',
            'type' => 'string',
        ],
        'user_phone' => [
            'field' => 'mobile',
            'type' => 'string',
        ],
        'user_name' => [
            'field' => 'user_name',
            'type' => 'string',
        ],
        'bus_code' => [
            'field' => 'bus_code',
            'type' => 'string',
        ],
        'sku_code' => [
            'field' => 'sku_code',
            'type' => 'string',
        ],
        'price' => [
            'field' => 'price',
            'type' => 'float',
        ],
        'quantity' => [
            'field' => 'quantity',
            'type' => 'int',
            'default' => 1,
        ],
        'total_amount' => [
            'field' => 'total_amount',
            'type' => 'float',
        ],
        'order_time' => [
            'field' => 'order_time',
            'type' => 'datetime',
            'format' => 'Y-m-d H:i:s',
        ],
        'order_status' => [
            'field' => 'order_status',
            'type' => 'int',
            'default' => 0,
        ],
        'type' => [
            'field' => 'type',
            'type' => 'string',
            'default' => '1',
        ],
        'province_code' => [
            'field' => 'province_code',
            'type' => 'string',
        ],
        'city_code' => [
            'field' => 'city_code',
            'type' => 'string',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 渠道特定映射规则
    |--------------------------------------------------------------------------
    |
    | 按渠道 PID 配置独立的映射规则，会覆盖默认规则。
    | 示例：鑫全域渠道（pid=186）使用不同的字段名
    |
    */
    'channels' => [
        // '186' => [
        //     'user_phone' => [
        //         'field' => 'phone',
        //         'type' => 'string',
        //     ],
        //     'order_time' => [
        //         'field' => 'create_time',
        //         'type' => 'datetime',
        //         'format' => 'Y-m-d H:i:s',
        //     ],
        // ],
    ],
];