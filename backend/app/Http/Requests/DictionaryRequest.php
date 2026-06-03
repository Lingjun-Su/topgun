<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DictionaryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('dictionary'); // 获取当前路由中的 ID (用于唯一性排除)

        return [
            'type'   => 'required|string|max:50',
            // code 在同一个 type 下应该是唯一的
            // 'code'   => "required|string|max:50|unique:dictionaries,code,{$id},id,type,{$this->type}",
            'code'   => [
                'required',
                'string',
                'max:50',
                // 核心逻辑：在同一个 type 下，code 必须唯一
                Rule::unique('dictionaries', 'code')
                    ->where('type', $this->type)
                    ->ignore($id),
            ],
            'label'  => 'required|string|max:50',
            'sort'   => 'integer|min:0',
            'status' => 'boolean',
        ];
    }
}
