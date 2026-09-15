<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'contract_no' => [
                'required',
                'string',
                Rule::unique('master_contracts', 'contract_no')->ignore($this->id),
            ], // 合同编号
            'title' => 'required|string|max:200', // 合同名称
            'org_a_id' => 'required|exists:organizations,id', // 甲方ID
            'org_b_id' => 'required|exists:organizations,id|different:org_a_id', // 乙方ID
            'total_limit' => 'required|numeric|min:0', // 金额
            'signed_date' => 'required|date', // 签订日期
            'effective_date' => 'required|date', // 开始时间
            'expiry_date' => 'required|date|after_or_equal:effective_date', // 终止时间
            'summary' => 'nullable|string', // 内容
            'remarks' => 'nullable|string', // 备注
            'version' => 'nullable|numeric', // 版本
            'status' => 'nullable|numeric', // 状态
            'signer_a' => 'nullable|string', // 甲方签订人
            'contact_a' => 'nullable|string', // 甲方联系人
            'contact_a_phone' => 'nullable|string', // 甲方联系电话
            'signer_b' => 'nullable|string', // 乙方签订人
            'contact_b' => 'nullable|string', // 乙方联系人
            'contact_b_phone' => 'nullable|string', // 乙方联系电话

        ];
    }

    public function messages(): array
    {
        return [
            'org_b_id.different' => '甲方和乙方不能为同一组织',
            'expiry_date.after_or_equal' => '到期日期不能早于生效日期',
        ];
    }
}
