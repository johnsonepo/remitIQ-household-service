<?php

namespace App\Http\Requests\Api\Household;

use App\Http\Requests\Api\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateHouseholdMemberRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in(['admin', 'member']),
            ],
        ];
    }
}
