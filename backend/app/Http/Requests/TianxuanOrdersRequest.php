<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTianxuanOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * 严格匹配您提供的参数表
     */
    public function rules(): array
    {
        return [
            'mobile'      => 'required|string|max:20',
            'pid'         => 'required|string|max:50',
            'bus_code'    => 'required|string|max:50',
            'sku_code'    => 'required|string|max:100',
            'order_no'    => 'required|string|max:100|unique:tianxuan_orders,order_no',
            'create_time' => 'required|string|size:10', // 10位时间戳
            'type'        => 'required|in:1,2',         // 1:平安健康 2:商超
            'platform'    => 'required|string',
            'pack'        => 'required|string',
            'url'         => 'required|url',
            'ip'          => 'required|ip',
            'sms_time'    => 'required|string|size:10',
            'code'        => 'required|string|max:20',
        ];
    }
}
