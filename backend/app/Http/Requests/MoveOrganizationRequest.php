<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->route('organization')->id;

        return [
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where(function ($query) use ($organizationId) {
                    $query->where('id', '!=', $organizationId);
                }),
            ],
        ];
    }
}
