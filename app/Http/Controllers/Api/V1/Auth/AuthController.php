<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AuthController extends BaseController
{
    /**
     * Create the authentication controller.
     *
     * AuthService contains the authentication business logic, allowing this
     * controller to remain focused on HTTP concerns: receiving validated
     * requests, invoking the appropriate service operation, transforming
     * resources, and returning API responses.
     */
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * POST /api/v1/auth/register
     *
     * Validate registration data, create the account, issue a JWT, and
     * return the newly-created user with its authentication token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            $request->validated()
        );

        return $this->created(
            data: $this->transformAuthResult($result),
            message: 'Account created successfully.',
        );
    }

    /**
     * POST /api/v1/auth/login
     *
     * Validate the supplied credentials and authenticate the user through
     * the API JWT guard.
     *
     * Invalid credentials are converted into a 401 Unauthorized response
     * rather than exposing authentication implementation details.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated()
        );

        if ($result === null) {
            return ApiResponse::unauthorized(
                'Invalid email or password.'
            );
        }

        return $this->success(
            data: $this->transformAuthResult($result),
            message: 'Login successful.',
        );
    }

    /**
     * POST /api/v1/auth/refresh
     *
     * Validate the current JWT through the authentication service and issue
     * a replacement access token.
     *
     * Token-related exceptions are deliberately translated here into a
     * generic 401 response so internal JWT implementation details are not
     * exposed to API clients.
     */
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refresh();

            return $this->success(
                data: $this->transformAuthResult($result),
                message: 'Token refreshed successfully.',
            );
        } catch (Throwable) {
            return ApiResponse::unauthorized(
                'Unable to refresh token.'
            );
        }
    }

    /**
     * Transform the service-level authentication result into the public
     * HTTP API representation.
     *
     * User models are wrapped in UserResource so the internal Eloquent
     * representation is never exposed directly by the API.
     */
    private function transformAuthResult(array $result): array
    {
        return [
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ];
    }
}