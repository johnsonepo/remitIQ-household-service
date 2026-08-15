<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Auth\VerifyEmailRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    ) {}

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
     * POST /api/v1/auth/logout
     *
     * Invalidates the currently authenticated JWT.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->success(
            message: 'Logout successful.',
        );
    }

    /**
     * GET /api/v1/auth/me
     *
     * Return the profile of the currently authenticated user.
     *
     * Authentication middleware guarantees that a valid JWT has already
     * been resolved before this action is reached.
     *
     * UserResource is used to keep the public API representation separate
     * from the internal Eloquent User model.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            data: new UserResource($request->user()),
            message: 'Profile retrieved successfully.',
        );
    }

    /**
     * PATCH /api/v1/auth/profile
     *
     * Update only the profile fields supplied by the client.
     *
     * UpdateProfileRequest uses "sometimes" validation rules, so this
     * endpoint follows PATCH semantics: clients only need to submit the
     * fields they want to change.
     */
    public function updateProfile(
        UpdateProfileRequest $request
    ): JsonResponse {
        $user = $this->authService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return $this->success(
            data: new UserResource($user),
            message: 'Profile updated successfully.',
        );
    }

    /**
     * PUT /api/v1/auth/profile/password
     *
     * Change the authenticated user's password.
     *
     * The service verifies the current password, updates the new password,
     * and invalidates the JWT used for this request. The client must
     * authenticate again after a successful password change.
     */
    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {
        $this->authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return $this->success(
            message: 'Password changed successfully. Please log in again.',
        );
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

    /**
     * POST /api/v1/auth/email/verify
     *
     * Body: { "id": 9, "hash": "..." } — sent by the frontend after
     * parsing the signed link from the verification email.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $this->authService->verifyEmail(
            $request->validated('id'),
            $request->validated('hash'),
        );

        return $this->success(message: 'Email verified successfully.');
    }

    /**
     * POST /api/v1/auth/email/resend
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $this->authService->resendVerificationEmail($request->user());

        return $this->success(message: 'Verification email sent.');
    }

    /**
     * POST /api/v1/auth/password/forgot
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword(
            $request->validated('email')
        );

        return $this->success(
            message: 'If an account exists for that email, a password reset link has been sent.'
        );
    }

    /**
     * POST /api/v1/auth/password/reset
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword(
            $request->validated('token'),
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->success(
            message: 'Password reset successfully.'
        );
    }
}
