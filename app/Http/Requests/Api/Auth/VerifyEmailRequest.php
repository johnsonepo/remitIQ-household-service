<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseFormRequest;

class VerifyEmailRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
            'hash' => ['required', 'string'],
        ];
    }
}