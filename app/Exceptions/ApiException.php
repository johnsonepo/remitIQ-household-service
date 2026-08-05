<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * ============================================================================
 * ApiException
 * ============================================================================
 *
 * Represents an operational exception within the application.
 *
 * This exception should be thrown whenever an expected business or application
 * error occurs. It allows services, repositories and controllers to abort
 * execution in a predictable manner while delegating HTTP response generation
 * to Laravel's centralized exception handler.
 *
 * This class does NOT generate JSON responses directly.
 * Response rendering is handled centrally in bootstrap/app.php.
 *
 * Examples
 * --------
 *
 * throw ApiException::notFound('Household not found.');
 *
 * throw ApiException::forbidden(
 *     'You are not allowed to modify this household.'
 * );
 *
 * throw ApiException::validation(
 *     'Invalid household data.',
 *     [
 *         'currency' => [
 *             'Unsupported currency.'
 *         ]
 *     ]
 * );
 *
 * ============================================================================
 */
class ApiException extends Exception
{
    /**
     * HTTP status code returned to the client.
     */
    protected int $statusCode;

    /**
     * Optional validation errors or additional error information.
     */
    protected mixed $errors;

    /**
     * Create a new ApiException instance.
     *
     * @param  int  $statusCode
     *                           HTTP status code.
     * @param  string  $message
     *                           Human-readable error message.
     * @param  mixed  $errors
     *                         Optional validation errors or additional error information.
     * @param  Throwable|null  $previous
     *                                    Previous exception for exception chaining.
     */
    public function __construct(
        int $statusCode,
        string $message,
        mixed $errors = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            code: $statusCode,
            previous: $previous,
        );

        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    /**
     * Get the HTTP status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get validation errors or additional error information.
     */
    public function errors(): mixed
    {
        return $this->errors;
    }

    /*
    |--------------------------------------------------------------------------
    | Factory Methods
    |--------------------------------------------------------------------------
    */

    /**
     * HTTP 400 Bad Request
     */
    public static function badRequest(
        string $message = 'Bad request.',
        mixed $errors = null,
    ): self {
        return new self(400, $message, $errors);
    }

    /**
     * HTTP 401 Unauthorized
     */
    public static function unauthorized(
        string $message = 'Unauthorized.',
        mixed $errors = null,
    ): self {
        return new self(401, $message, $errors);
    }

    /**
     * HTTP 403 Forbidden
     */
    public static function forbidden(
        string $message = 'Forbidden.',
        mixed $errors = null,
    ): self {
        return new self(403, $message, $errors);
    }

    /**
     * HTTP 404 Not Found
     */
    public static function notFound(
        string $message = 'Resource not found.',
        mixed $errors = null,
    ): self {
        return new self(404, $message, $errors);
    }

    /**
     * HTTP 409 Conflict
     */
    public static function conflict(
        string $message = 'Conflict.',
        mixed $errors = null,
    ): self {
        return new self(409, $message, $errors);
    }

    /**
     * HTTP 422 Validation Failed
     */
    public static function validation(
        string $message = 'Validation failed.',
        mixed $errors = null,
    ): self {
        return new self(422, $message, $errors);
    }

    /**
     * HTTP 429 Too Many Requests
     */
    public static function tooManyRequests(
        string $message = 'Too many requests.',
        mixed $errors = null,
    ): self {
        return new self(429, $message, $errors);
    }

    /**
     * HTTP 500 Internal Server Error
     */
    public static function internal(
        string $message = 'Internal server error.',
        mixed $errors = null,
        ?Throwable $previous = null,
    ): self {
        return new self(
            500,
            $message,
            $errors,
            $previous,
        );
    }
}
