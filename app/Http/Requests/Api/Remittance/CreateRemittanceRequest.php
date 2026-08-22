<?php

namespace App\Http\Requests\Api\Remittance;

use App\Http\Requests\Api\BaseFormRequest;

class CreateRemittanceRequest extends BaseFormRequest
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
                'required',
                'uuid',
                'exists:households,id',
            ],

            'transfer_provider_id' => [
                'nullable',
                'uuid',
                'exists:transfer_providers,id',
            ],

            'amount_sent' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'sent_currency_code' => [
                'required',
                'string',
                'size:3',
                'alpha',
                'uppercase',
            ],

            'amount_received' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'received_currency_code' => [
                'required',
                'string',
                'size:3',
                'alpha',
                'uppercase',
            ],

            'exchange_rate' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'rate_source' => [
                'nullable',
                'string',
                'max:255',
            ],

            'sent_at' => [
                'required',
                'date_format:Y-m-d',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
