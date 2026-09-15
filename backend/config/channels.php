<?php

/**
 * 第三方渠道服务配置
 *
 * 配置项说明：
 * - base_url: API 请求地址（区分测试/生产环境）
 * - key: API 签名密钥
 * - pid: 渠道标识
 * - bus_code: 业务编码
 * - sku_code: 产品编码
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 鑫全域 (Quanyu) 渠道配置
    |--------------------------------------------------------------------------
    */
    'quanyu' => [
        // API 地址
        'base_url' => env('QUANYU_BASE_URL', 'http://cladmin.xinquanyu.top'),

        // API 签名密钥
        'key' => env('QUANYU_API_KEY', ''),

        // 业务参数
        'pid' => env('QUANYU_PID', '186'),
        'bus_code' => env('QUANYU_BUS_CODE', '96339749'),
        'sku_code' => env('QUANYU_SKU_CODE', 'SCJKGJHYYK'),

        // 超时设置（秒）
        'timeout' => env('QUANYU_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | 其他第三方渠道配置
    |--------------------------------------------------------------------------
    | 按需添加其他渠道配置
    */
    // 'tianxuan' => [
    //     'base_url' => env('TIANXUAN_BASE_URL', ''),
    //     'key' => env('TIANXUAN_API_KEY', ''),
    // ],

];
