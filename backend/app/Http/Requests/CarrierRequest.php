<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 实际开发请根据权限设置
    }

    public function rules(): array
    {
        // 获取路由中的 ID 以便在更新时排除唯一性检查
        $id = $this->route('carrier');

        return [
            'name'          => 'required|string|max:100',
            'short_name'    => 'nullable|string|max:30',
            'code'          => [
                'required',
                'string',
                'max:16',
                Rule::unique('carrier', 'code')->ignore($id)
            ],
            'contact_person' => 'string|nullable|max:50',
            'contact_phone'  => 'nullable|string|max:20',
            'address'        => 'nullable|string',
            'contents'       => 'nullable|string',
            'status'         => 'integer',
        ];
    }
    public function messages(): array
    {
        return [
            'name' => '名称必须有',
            'short_name' => '简称必须有',
            'code'=>'编号必须有',
            'contact_person'=>'联系人必须有',
            'contact_phone'=>'联系人必须有',
        ];
    }
}
