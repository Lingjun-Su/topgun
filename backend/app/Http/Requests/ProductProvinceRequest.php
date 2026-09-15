<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 使用 '.' 代表验证根数据对象
            // '.' => 'present|array|min:0',
            '*' => 'string|max:20',
        ];
    }

    /**
     * 严谨做法：自定义错误键名的显示名称
     */
    public function attributes(): array
    {
        return [
            // '.' => '省份数据列表',
            '*' => '省份代码',
        ];
    }
}
