<?php

namespace App\Http\Requests\Api\Household;

use App\Http\Requests\Api\BaseFormRequest;

class UpdateHouseholdRequest extends BaseFormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'base_currency_code' => ['sometimes', 'string', 'size:3', 'alpha'],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone'],
        ];
    }
}
