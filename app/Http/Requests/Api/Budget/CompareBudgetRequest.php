<?php

namespace App\Http\Requests\Api\Budget;

use App\Http\Requests\Api\BaseFormRequest;

class CompareBudgetRequest extends BaseFormRequest
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
            'household_id' => [
                'required',
                'uuid',
            ],
            'month' => [
                'required',
                'date',
            ],
            'compare_month' => [
                'required',
                'date',
            ],
        ];
    }
}
