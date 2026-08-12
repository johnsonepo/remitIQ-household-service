<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    /**
     * POST /api/v1/auth/register
     *
     * Creates a new user account and immediately issues a JWT, so
     * the client doesn't need a separate login call right after
     * registering.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'), // hashed via the 'hashed' cast on User
            'country_code' => $request->validated('country_code'),
            'phone' => $request->validated('phone'),
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->created(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ],
            message: 'Account created successfully.',
        );
    }

    /**
     * POST /api/v1/auth/login
     *
     * Authenticates a user and issues a JWT.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return ApiResponse::unauthorized('Invalid email or password.');
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $this->success(
            data: [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ],
            message: 'Login successful.',
        );
    }
}
