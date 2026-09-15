<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('business'); // 获取路由中的 ID 用于排除自身

        return [
            'org_id' => 'required|integer',
            'carrier_id' => 'required|integer',
            'name' => 'required|string|max:200',
            'code' => [
                'required',
                'string',
                // 严谨：在同一 org_id 下唯一，且排除已逻辑删除的记录（可选）
                Rule::unique('business')->where(fn ($q) => $q->where('org_id', $this->org_id)->whereNull('deleted_at')
                )->ignore($id),
            ],
            'short_name' => 'required|string|max:50',
            'status' => 'integer',
            'contact_person' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'address' => 'nullable|string',
            'contents' => 'nullable|string',
            // 'type' => 'required|in:CUSTOMER,SUPPLIER,BOTH',
        ];
    }

    public function messages(): array
    {
        return [
            'name' => '名称必须要有',
            'code.unique' => '业务编码不能重复',
        ];
    }
}
