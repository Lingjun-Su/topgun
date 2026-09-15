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
            'name' => 'required|string|max:100',
            // pid_value 必须全局唯一（排除自己），防止多个渠道混淆
            'pid' => [
                'required',
                'string',
                Rule::unique('third_channels', 'pid')->ignore($id),
            ],
            'key' => 'required|string|max:128',
            'status' => 'required|in:0,1,2',
            // 必须在这里声明，哪怕只是 simple 'nullable'
            'parameter_name1' => 'nullable|string',
            'parameter_value1' => 'nullable|string',
            'parameter_name2' => 'nullable|string',
            'parameter_value2' => 'nullable|string',
            'parameter_name3' => 'nullable|string',
            'parameter_value3' => 'nullable|string',
            'parameter_name4' => 'nullable|string',
            'parameter_name5' => 'nullable|string',
            'parameter_value5' => 'nullable|string',
            'organization_id' => 'nullable|integer',
            'method' => 'string',
            'role' => 'nullable|string|in:unset,supplier_a,channel_c',
            // 核心逻辑：当 is_ip_restricted 为 true (或 1) 时，ip_whitelist 必须存在且不能为空
            'is_ip_restricted' => 'required|boolean',
            'ip_whitelist' => [
                'nullable',
                'string',
                'required_if:is_ip_restricted,true',
                function ($attribute, $value, $fail) {
                    if ($this->is_ip_restricted && ! empty($value)) {
                        $ips = preg_split('/[\s,]+/', $value);
                        foreach ($ips as $ip) {
                            if (! filter_var($ip, FILTER_VALIDATE_IP) && ! str_contains($ip, '/')) {
                                $fail("白名单包含无效的 IP 地址: {$ip}");
                            }
                        }
                    }
                },
            ],
            // ====== 推送配置 ======
            'push_base_url' => 'nullable|string|max:255',
            'push_endpoint' => 'nullable|string|max:255',
            'push_timeout' => 'nullable|integer|min:1|max:300',
            // ====== 签名配置 ======
            'sign_algorithm' => 'nullable|string|in:sha256,md5,hmac_sha256',
            'sign_key' => 'nullable|string|max:255',
            // ====== 回调配置 ======
            'callback_url' => 'nullable|string|max:255',
            // ====== 接收上家信息配置 ======
            'receive_endpoint' => 'nullable|string|max:255',
            'receive_auth_mode' => 'nullable|string|in:signature,header,none',
            'auto_forward' => 'boolean',
            'forward_target_pid' => 'nullable|string|max:50',
            // ====== 向下家推送信息配置 ======
            'push_notify_url' => 'nullable|string|max:255',
            'push_max_retries' => 'nullable|integer|min:0|max:100',
            'push_retry_delay' => 'nullable|integer|min:1|max:3600',
            // ====== 定时回调推送 ======
            'auto_callback_enabled' => 'boolean',
            'auto_callback_cron' => 'nullable|string|max:20',
            'auto_callback_time_start' => 'nullable|string|max:5',
            'auto_callback_time_end' => 'nullable|string|max:5',
            // ====== 服务映射 ======
            'service_class' => 'nullable|string|max:255',
            // ====== 扩展配置 ======
            'ext_config' => 'nullable|array',
            // ====== 推送成功/失败判断规则 ======
            'push_success_rule' => 'nullable|array',
            'push_fail_rule' => 'nullable|array',
            // ====== 多步骤推送配置 ======
            'push_steps' => 'nullable|array',
            // ====== 回调接收配置 ======
            'callback_config' => 'nullable|array',
            // ====== 入站映射配置（order/verify 入口分段）=====
            'receive_mapping' => 'nullable|array',
            'receive_mapping.order' => 'nullable|array',
            'receive_mapping.verify' => 'nullable|array',
            // ====== 停止条件配置 ======
            'stop_rules' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'pid.unique' => '该 PID 已被其他渠道占用',
            'ip_whitelist' => '当打开强制使用IP白名单时，IP白名单不能为空，且必须为IP地址',
        ];
    }
}
