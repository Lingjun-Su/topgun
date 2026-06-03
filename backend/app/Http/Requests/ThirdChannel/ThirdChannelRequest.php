<?php

namespace App\Http\Requests\ThirdChannel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ThirdChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('channel'); // 获取当前编辑的 ID

        return [
            'name'      => 'required|string|max:100',
            // pid_value 必须全局唯一（排除自己），防止多个渠道混淆
            // 'pid' => 'required|string|max:50|unique:third_channels,pid,' . $id,
            'pid' => [
                'required',
                'string',
                Rule::unique('third_channels', 'pid')->ignore($id),
            ],
            'key'   => 'required|string|max:128',
            'status'    => 'required|in:0,1,2',
            // 必须在这里声明，哪怕只是 simple 'nullable'
            'parameter_name1' => 'nullable|string',
            'parameter_value1' => 'nullable|string',
            'parameter_name2' => 'nullable|string',
            'parameter_value2' => 'nullable|string',
            'parameter_name3' => 'nullable|string',
            'parameter_value3' => 'nullable|string',
            'parameter_name4' => 'nullable|string',
            'parameter_name4' => 'nullable|string',
            'parameter_name5' => 'nullable|string',
            'parameter_value6' => 'nullable|string',
            'organization_id' => 'nullable|integer',
            'method' => 'string',
            // 核心逻辑：当 is_ip_restricted 为 true (或 1) 时，ip_whitelist 必须存在且不能为空
            'is_ip_restricted' => 'required|boolean',
            'ip_whitelist' => [
                'nullable',
                'string',
                // 使用 Laravel 验证规则：当 is_ip_restricted 等于 true 时必填
                'required_if:is_ip_restricted,true',
                // 自定义校验逻辑（可选）：验证 IP 格式是否合法
                function ($attribute, $value, $fail) {
                    if ($this->is_ip_restricted && !empty($value)) {
                        $ips = preg_split('/[\s,]+/', $value);
                        foreach ($ips as $ip) {
                            // 简单的格式校验，支持 IPv4
                            if (!filter_var($ip, FILTER_VALIDATE_IP) && !str_contains($ip, '/')) {
                                $fail("白名单包含无效的 IP 地址: {$ip}");
                            }
                        }
                    }
                },
            ],

        ];
    }

    public function messages(): array
    {
        return [
            'pid.unique' => '该 PID 已被其他渠道占用',
            'ip_whitelist'=>'当打开强制使用IP白名单时，IP白名单不能为空，且必须为IP地址'
        ];
    }
}
