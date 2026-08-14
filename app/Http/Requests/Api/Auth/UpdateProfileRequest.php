<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\Api\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes', 'nullable', 'string', 'max:50', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
