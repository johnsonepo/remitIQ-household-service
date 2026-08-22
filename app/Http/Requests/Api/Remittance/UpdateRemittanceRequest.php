<?php

namespace App\Http\Requests\Api\Remittance;

use App\Http\Requests\Api\BaseFormRequest;

class UpdateRemittanceRequest extends BaseFormRequest
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
                'nullable',
                'uuid',
                'exists:transfer_providers,id',
            ],

            'amount_sent' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'sent_currency_code' => [
                'sometimes',
                'string',
                'size:3',
                'alpha',
                'uppercase',
            ],

            'amount_received' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'received_currency_code' => [
                'sometimes',
                'string',
                'size:3',
                'alpha',
                'uppercase',
            ],

            'exchange_rate' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'rate_source' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'sent_at' => [
                'sometimes',
                'date_format:Y-m-d',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
