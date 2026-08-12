<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
}
