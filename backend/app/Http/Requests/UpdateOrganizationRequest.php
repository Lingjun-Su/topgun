<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:50',
            'parent_id' => [
                'nullable',
                // 'exists:organizations,id',
                // 确保父节点不是自己
                function ($attribute, $value, $fail) {
                    if ($value === 0) return;
                    if ($value == $this->route('organization')) {
                        $fail('上级组织不能是组织本身。');
                    }
                },
            ],
            'bankAccounts' => 'array',
            'bankAccounts.*.id' => 'nullable|exists:organization_banks,id',
            'bankAccounts.*.bank_name' => 'required_with:bankAccounts',
            'bankAccounts.*.bank_account' => 'required_with:bankAccounts',
            'address'=>'nullable|string',//
            'short_name'=>'nullable|string',//
            'contact_person'=>'nullable|string',//
            'contact_phone'=>'nullable|string',//
            'province_code'=>'nullable|string',//
            'city_code'=>'nullable|string',//
            'district_code'=>'nullable|string',//
            'street_code'=>'nullable|string',//
            'social_credit_code'=>'nullable|string',
            'remark'=>'nullable|string',
            'status'=>'integer'
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => '父级组织不存在或不能设置为自己',
            'code.unique' => '组织编码已存在',
        ];
    }
}
