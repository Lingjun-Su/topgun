<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreasRequest extends FormRequest
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
            'name' => 'nullable|string|max:100',
            'short_name' => 'nullable|string|max:30',
            'level' => 'nullable|integer',
            'parent_code' => 'nullable|string',
            'code' => [
                'nullable',
                'string',
                'max:16',
                Rule::unique('carrier', 'code')->ignore($id),
            ],
        ];
    }
}
