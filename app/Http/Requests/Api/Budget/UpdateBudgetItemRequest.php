<?php

namespace App\Http\Requests\Api\Budget;

use App\Http\Requests\Api\BaseFormRequest;

class UpdateBudgetItemRequest extends BaseFormRequest
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
            'budget_category_id' => [
                'sometimes',
                'uuid',
            ],
            'planned_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'actual_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
