<?php

// app/Http/Requests/ContractUpdateRequest.php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterContractRequest extends FormRequest
{
    public function authorize()
    {
        // 具体权限在控制器中通过 Policy 再次检查，这里暂时允许
        return true;
    }

    public function rules()
    {
        return [
            'status' => 'required|numeric',
            // 'title'   => 'required|string|max:255',
            // 'content' => 'required|string',
            // 'party_a' => 'required|string|max:255',
            // 'party_b' => 'required|string|max:255',
        ];
    }
}
