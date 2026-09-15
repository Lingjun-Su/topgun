<?php

namespace App\Http\Requests\ThirdChannel;

use Illuminate\Foundation\Http\FormRequest;

class OrderProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:100',
            'pid'       => 'required|integer',
            'remark'    => 'nullable|string|max:128',
            'status'    => 'required|in:0,1',
            'code'      => 'required|string|max:20',
        ];
    }
}
