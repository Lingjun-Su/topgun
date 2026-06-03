<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUnsubscribeRequest extends FormRequest
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
            'order_no' => 'required|string',
            'create_time' => 'required|string|size:10',
        ];
    }

    public function messages()
    {
        return [
            'mobile.required' => '手机号不能为空',
            'mobile.size' => '手机号长度必须为11位',
            'pid.required' => '渠道ID不能为空',
            'bus_code.required' => '业务标识不能为空',
            'order_no.required' => '订单号不能为空',
            'create_time.required' => '退订时间不能为空',
            'create_time.size' => '时间戳必须为10位',
        ];
    }
}
