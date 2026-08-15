<?php

namespace App\Http\Requests\Api\Household;

use App\Http\Requests\Api\BaseFormRequest;
use Illuminate\Validation\Rule;

class AddHouseholdMemberRequest extends BaseFormRequest
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'role' => [
                'sometimes',
                'string',
                Rule::in(['admin', 'member']),
            ],
        ];
    }
}
