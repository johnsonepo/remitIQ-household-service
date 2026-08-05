<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * ============================================================================
 * Base API Controller
 * ============================================================================
 *
 * Base controller for all RemitIQ API controllers.
 *
 * Provides:
 *
 * ✓ Standard success responses
 * ✓ Standard error responses
 * ✓ Consistent API behavior
 * ✓ Future extension point
 *
 * Example:
 *
 * class HouseholdController extends BaseController
 * {
 *     public function index()
 *     {
 *         return $this->success(
 *             $households,
 *             'Households retrieved successfully.'
 *         );
 *     }
 * }
 *
 * ============================================================================
 */
abstract class BaseController extends Controller
{
    /**
     * Return successful API response.
     */
    protected function success(
        mixed $data = null,
        string $message = 'Request successful.',
        int $status = 200,
        array $meta = []
    ): JsonResponse {

        return ApiResponse::success(
            data: $data,
            message: $message,
            status: $status,
            meta: $meta
        );
    }


    /**
     * Return error API response.
     */
    protected function error(
        string $message = 'Request failed.',
        int $status = 400,
        mixed $errors = null,
        array $meta = []
    ): JsonResponse {

        return ApiResponse::error(
            message: $message,
            status: $status,
            errors: $errors,
            meta: $meta
        );
    }


    /**
     * Return created response.
     */
    protected function created(
        mixed $data = null,
        string $message = 'Resource created successfully.',
        array $meta = []
    ): JsonResponse {

        return ApiResponse::created(
            data: $data,
            message: $message,
            meta: $meta
        );
    }


    /**
     * Return paginated response.
     */
    protected function paginated(
        mixed $paginator,
        string $message = 'Data retrieved successfully.'
    ): JsonResponse {

        return ApiResponse::paginated(
            paginator: $paginator,
            message: $message
        );
    }
}
