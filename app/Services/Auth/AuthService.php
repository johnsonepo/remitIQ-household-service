<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Register a new user account and issue an authentication token.
     *
     * The service is responsible for the application-level authentication
     * workflow:
     *
     * 1. Create the user from the validated registration data.
     * 2. Generate a JWT for the newly-created user.
     * 3. Return the user and token information in the standard
     *    authentication response structure.
     *
     * Password hashing is intentionally not performed here. The User model
     * is responsible for hashing the password through its configured
     * 'hashed' cast when the model is persisted.
     *
     * The controller remains responsible for request validation and
     * transforming the returned User model into the API resource format.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     user: User,
     *     token: string,
     *     token_type: string,
     *     expires_in: int
     * }
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'country_code' => $data['country_code'],
            'phone' => $data['phone'],
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->tokenResponse($user, $token);
    }

    /**
     * Authenticate a user using the supplied credentials.
     *
     * The JWT authentication guard verifies the credentials and, when they
     * are valid, issues a signed JWT.
     *
     * A null result indicates that authentication failed. The controller
     * converts this outcome into the appropriate HTTP 401 response.
     *
     * Keeping the failed-authentication decision outside this service avoids
     * coupling the service to HTTP-specific response handling.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{
     *     user: User,
     *     token: string,
     *     token_type: string,
     *     expires_in: int
     * }|null
     */
    public function login(array $credentials): ?array
    {
        $token = Auth::guard('api')->attempt($credentials);

        if (! $token) {
            return null;
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        if (! $user->is_active) {
            Auth::guard('api')->logout(); // invalidate the token we just issued
            return null;
        }

        return $this->tokenResponse($user, $token);
    }

    /**
     * Refresh the currently authenticated JWT.
     *
     * The JWT package validates the current token and replaces it with a
     * newly-issued token. The refreshed token receives a new expiration
     * period based on the configured JWT TTL.
     *
     * This method expects the API authentication middleware to have already
     * established the authenticated context before it is called.
     *
     * Token refresh failures are intentionally allowed to propagate to the
     * controller, where they can be translated into the appropriate API
     * response.
     *
     * @return array{
     *     user: User,
     *     token: string,
     *     token_type: string,
     *     expires_in: int
     * }
     */
    public function refresh(): array
    {
        $token = Auth::guard('api')->refresh();

        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $this->tokenResponse($user, $token);
    }

    /**
     * Logout the currently authenticated user by invalidating
     * the JWT presented with the current request.
     *
     * JWT authentication is stateless, so logout does not destroy
     * a server-side session. Instead, the current token is added to
     * the JWT blacklist and can no longer be used for authentication.
     *
     * The JWT package must have blacklist support enabled for this
     * invalidation mechanism to work correctly.
     */
    public function logout(): void
    {
        Auth::guard('api')->logout();
    }

    /**
     * Build the standard authentication token payload.
     *
     * All authentication operations that issue a JWT use this method so
     * registration, login, and refresh return the same response structure.
     *
     * Keeping this normalization in one place prevents duplicated JWT
     * metadata logic across the individual authentication methods.
     *
     * The expiration value is returned in seconds because this is generally
     * more convenient for API clients than the package's internal TTL value,
     * which is expressed in minutes.
     *
     * @return array{
     *     user: User,
     *     token: string,
     *     token_type: string,
     *     expires_in: int
     * }
     */
    private function tokenResponse(User $user, string $token): array
    {
        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ];
    }
}
