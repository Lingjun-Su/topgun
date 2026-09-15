<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ChannelApiRequest extends FormRequest
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
            // 'pid' => 'required|string|max:50|unique:channels_api,pid,' . $id,
            'pid' => [
                'required',
                'string',
                Rule::unique('channels_api', 'pid')->ignore($id),
            ],
            'key'   => 'required|string|max:128',
            'status'    => 'required|in:0,1',
            // 必须在这里声明，哪怕只是 simple 'nullable'
            'parameter_1' => 'nullable|string',
            'parameter_2' => 'nullable|string',
            'parameter_3' => 'nullable|string',
            'parameter_4' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'pid.unique' => '该 PID 已被其他渠道占用',
        ];
    }
}
