<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:organizations,id',
            'code' => 'nullable|string|max:50|unique:organizations,code',
            'description' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'nullable|in:1,0',
            'type'=>'required|integer',
            // 必须添加下面这行，validated() 才会包含 bankAccounts
            'bankAccounts' => 'array',

            // 如果想更严谨，可以校验数组内部的字段
            'bankAccounts.*.bank_name' => 'nullable|string',
            'bankAccounts.*.bank_account' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '组织名称不能为空',
            'name.max' => '组织名称不能超过255个字符',
            'parent_id.exists' => '父级组织不存在',
            'code.unique' => '组织编码已存在',
            'type.required'=>'类型不能为空',
        ];
    }
}
