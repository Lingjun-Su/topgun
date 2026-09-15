<?php

// app/Http/Requests/ThirdParty/ReceiveRequest.php

namespace App\Http\Requests\ThirdChannel;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 签名校验在 Controller 做，此处仅做格式准入
    }

    public function rules(): array
    {
        return [
            'pid' => 'required|string|exists:third_channels,pid',
            'timestamp' => 'required|integer',
            'data' => 'required|array',
            // 'url'       => 'sometime|string',
            'sign' => 'required|string', // 假设签名在 Body 中传递
        ];
    }

    public function messages(): array
    {
        return [
            'pid.exists' => '渠道标识不存在',
            'data.array' => '数据包格式必须为 JSON 对象/数组',
        ];
    }
}
