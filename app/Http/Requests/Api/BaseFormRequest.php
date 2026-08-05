<?php

namespace App\Http\Requests\Api;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ============================================================================
 * Base API Form Request
 * ============================================================================
 *
 * Base validation class for all RemitIQ API requests.
 *
 * All module request validators should extend this class.
 *
 * Example:
 *
 * class CreateHouseholdRequest extends BaseFormRequest
 * {
 *     public function rules(): array
 *     {
 *         return [
 *             'name' => [
 *                 'required',
 *                 'string',
 *                 'max:255'
 *             ]
 *         ];
 *     }
 * }
 *
 *
 * Responsibilities:
 *
 * ✓ Centralized validation handling
 * ✓ Consistent API error response
 * ✓ JSON API friendly
 * ✓ Removes validation logic from controllers
 *
 * ============================================================================
 */
abstract class BaseFormRequest extends FormRequest
{
    /**
     * Determine if user is authorized.
     *
     * Authentication rules will be added later
     * with JWT middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle failed validation.
     *
     * Laravel normally redirects for web requests.
     * APIs should always return JSON.
     */
    protected function failedValidation(
        Validator $validator
    ): void {

        throw new HttpResponseException(
            ApiResponse::validation(
                errors: $validator->errors()
            )

        );
    }
}
