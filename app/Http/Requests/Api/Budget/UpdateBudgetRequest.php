<?php

namespace App\Http\Requests\Api\Budget;

use App\Http\Requests\Api\BaseFormRequest;

class UpdateBudgetRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['sometimes', 'date_format:Y-m-d'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'alpha', 'uppercase'],
            'total_planned' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
