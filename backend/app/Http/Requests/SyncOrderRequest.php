<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'mobile' => 'required|string|size:11',
            'pid' => 'required|string',
            'bus_code' => 'required|string',
            'sku_code' => 'required|string',
            'order_no' => 'required|string',
            'create_time' => 'required|string|size:10',
            'type' => 'required|string|in:1,2,125',
            'platform' => 'required|string',
            'pack' => 'required|string',
            'url' => 'required|string|url',
            'ip' => 'required|ip',
            'sms_time' => 'required|string|size:10',
            'code' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'mobile.required' => '手机号不能为空',
            'mobile.size' => '手机号长度必须为11位',
            'pid.required' => '渠道ID不能为空',
            'bus_code.required' => '业务标识不能为空',
            'sku_code.required' => '产品标识不能为空',
            'order_no.required' => '订单号不能为空',
            'create_time.required' => '订购时间不能为空',
            'create_time.size' => '时间戳必须为10位',
            'type.required' => '类型不能为空',
            'type.in' => '类型值无效',
            'platform.required' => '投放平台不能为空',
            'pack.required' => 'APP包名不能为空',
            'url.required' => '推广落地页地址不能为空',
            'url.url' => 'URL格式无效',
            'ip.required' => 'IP地址不能为空',
            'ip.ip' => 'IP地址格式无效',
            'sms_time.required' => '验证码下发时间不能为空',
            'sms_time.size' => '时间戳必须为10位',
            'code.required' => '验证码不能为空',
        ];
    }
}
