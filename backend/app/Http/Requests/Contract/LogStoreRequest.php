<?php

namespace App\Http\Requests\contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 实际开发中可在此处校验审核权限
    }

    public function rules(): array
    {
        return [
            'master_id'   => 'required|integer|exists:master_contracts,id',
            'action_type' => ['required', Rule::in([0, 1, 2, 3, 4])],//当前状态
            'remark'      => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'master_id.exists' => '关联的合同记录不存在',
            'action_type.in'   => '非法的操作动作',
        ];
    }
}
