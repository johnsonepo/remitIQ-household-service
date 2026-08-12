<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
 * Failed validation is intentionally NOT overridden here — Laravel's
 * default FormRequest::failedValidation() throws ValidationException,
 * which is already caught and formatted centrally in bootstrap/app.php
 * via ApiResponse::validation(). Overriding it here would create a
 * second, parallel formatting path that could drift out of sync with
 * the central handler.
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
}
