<?php

namespace App\Http\Requests\Api\Remittance;

use App\Http\Requests\Api\BaseFormRequest;

class RemittanceAnalyticsRequest extends BaseFormRequest
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
            'household_id' => [
                'sometimes',
                'uuid',
                'exists:households,id',
            ],

            'transfer_provider_id' => [
                'sometimes',
                'uuid',
                'exists:transfer_providers,id',
            ],

            'from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],

            'to' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ];
    }
}
