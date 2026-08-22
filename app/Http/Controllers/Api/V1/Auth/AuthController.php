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
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created(data: $this->transformAuthResult($result), message: 'Account created successfully.');
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return ApiResponse::unauthorized('Invalid email or password.');
        }

        return $this->success(data: $this->transformAuthResult($result), message: 'Login successful.');
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refresh();

            return $this->success(data: $this->transformAuthResult($result), message: 'Token refreshed successfully.');
        } catch (Throwable) {
            return ApiResponse::unauthorized('Unable to refresh token.');
        }
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->success(data: null, message: 'Logout successful.');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(data: new UserResource($request->user()), message: 'Profile retrieved successfully.');
    }

    /**
     * PATCH /api/v1/auth/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return $this->success(data: new UserResource($user), message: 'Profile updated successfully.');
    }

    /**
     * PUT /api/v1/auth/profile/password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return $this->success(data: null, message: 'Password changed successfully. Please log in again.');
    }

    /**
     * Transform the service-level authentication result into the public
     * HTTP API representation.
     *
     * @param array{
     *     user: User,
     *     token: string,
     *     token_type: string,
     *     expires_in: int
     * } $result
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
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $this->authService->verifyEmail($request->validated('id'), $request->validated('hash'));

        return $this->success(data: null, message: 'Email verified successfully.');
    }

    /**
     * POST /api/v1/auth/email/resend
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $this->authService->resendVerificationEmail($request->user());

        return $this->success(data: null, message: 'Verification email sent.');
    }

    /**
     * POST /api/v1/auth/password/forgot
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return $this->success(data: null, message: 'If an account exists for that email, a password reset link has been sent.');
    }

    /**
     * POST /api/v1/auth/password/reset
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated('token'), $request->validated('email'), $request->validated('password'));

        return $this->success(data: null, message: 'Password reset successfully.');
    }
}
