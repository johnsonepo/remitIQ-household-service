<?php

namespace App\Http\Requests\Api\Budget;

use App\Http\Requests\Api\BaseFormRequest;

class CreateBudgetCategoryRequest extends BaseFormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'icon' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'color' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ];
    }
}
