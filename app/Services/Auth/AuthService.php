<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Notification\NotificationEventBuilder;
use App\Services\Notification\NotificationEventEmitter;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    public function __construct(private readonly NotificationEventBuilder $notificationEventBuilder, private readonly NotificationEventEmitter $notificationEventEmitter) {}

    /**
     * Register a new user account and issue an authentication token.
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
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'country_code' => $data['country_code'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        $user->sendEmailVerificationNotification();

        $event = $this->notificationEventBuilder->build(eventType: 'USER_REGISTERED', userId: (string) $user->id, data: [
            'userId' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ], );

        $this->notificationEventEmitter->emit($event);

        $token = JWTAuth::fromUser($user);

        return $this->tokenResponse($user, $token);
    }

    /**
     * Authenticate a user using the supplied credentials.
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
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        $token = $guard->attempt($credentials);

        if (! $token) {
            return null;
        }

        /** @var User $user */
        $user = $guard->user();

        if (! $user->is_active) {
            Auth::guard('api')->logout();

            return null;
        }

        $event = $this->notificationEventBuilder->build(eventType: 'USER_LOGGED_IN', userId: (string) $user->id, data: [
            'userId' => $user->id,
            'email' => $user->email,
        ], );

        $this->notificationEventEmitter->emit($event);

        return $this->tokenResponse($user, $token);
    }

    /**
     * Refresh the currently authenticated JWT.
     *
     * @return array{
     *     user: User,
     *     token: string,
     *     token_type: string,
     *     expires_in: int
     * }
     *
     * @throws ApiException If the authenticated user is inactive.
     */
    public function refresh(): array
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        /** @var User $user */
        $user = $guard->user();

        if (! $user->is_active) {
            $guard->logout();

            throw ApiException::unauthorized('Unable to refresh token.');
        }

        $token = $guard->refresh();

        /** @var User $refreshedUser */
        $refreshedUser = $guard->user();

        return $this->tokenResponse($refreshedUser, $token);
    }

    /**
     * Logout the currently authenticated user.
     */
    public function logout(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        Auth::guard('api')->logout();

        if ($user !== null) {
            $event = $this->notificationEventBuilder->build(eventType: 'USER_LOGGED_OUT', userId: (string) $user->id, data: [
                'userId' => $user->id,
                'email' => $user->email,
            ], );

            $this->notificationEventEmitter->emit($event);
        }
    }

    /**
     * Update the authenticated user's profile fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }

    /**
     * Change the authenticated user's password.
     *
     * @throws ApiException When the current password is incorrect.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! $user->is_active) {
            Auth::guard('api')->logout();

            throw ApiException::unauthorized('Unable to change password.');
        }

        if (! Hash::check($currentPassword, $user->password)) {
            throw ApiException::badRequest('Current password is incorrect.');
        }

        $user->update([
            'password' => $newPassword,
        ]);

        Auth::guard('api')->logout();

        $event = $this->notificationEventBuilder->build(eventType: 'PASSWORD_CHANGED', userId: (string) $user->id, data: [
            'userId' => $user->id,
            'email' => $user->email,
        ], );

        $this->notificationEventEmitter->emit($event);
    }

    /**
     * Build the standard authentication token payload.
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

    /**
     * Verify a user's email using the id/hash pair from the signed
     * verification link.
     *
     * @throws ApiException if the hash doesn't match, or the email is
     *                      already verified.
     */
    public function verifyEmail(int $id, string $hash): void
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw ApiException::badRequest('Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            throw ApiException::conflict('Email already verified.');
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        $event = $this->notificationEventBuilder->build(eventType: 'EMAIL_VERIFIED', userId: (string) $user->id, data: [
            'userId' => $user->id,
            'email' => $user->email,
        ], );

        $this->notificationEventEmitter->emit($event);
    }

    /**
     * Resend the verification email to the authenticated user.
     *
     * @throws ApiException if the email is already verified.
     */
    public function resendVerificationEmail(User $user): void
    {
        if (! $user->is_active) {
            throw ApiException::unauthorized('Unable to resend verification email.');
        }

        if ($user->hasVerifiedEmail()) {
            throw ApiException::conflict('Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        $event = $this->notificationEventBuilder->build(eventType: 'EMAIL_VERIFICATION_RESENT', userId: (string) $user->id, data: [
            'userId' => $user->id,
            'email' => $user->email,
        ], );

        $this->notificationEventEmitter->emit($event);
    }

    /**
     * Send a password reset link to the supplied email address.
     */
    public function forgotPassword(string $email): void
    {
        Password::broker()->sendResetLink([
            'email' => $email,
        ]);
    }

    /**
     * Reset a user's password using a valid password reset token.
     *
     * @throws ApiException When the token is invalid or expired.
     */
    public function resetPassword(string $token, string $email, string $password): void
    {
        $resetUser = null;

        $status = Password::broker()->reset([
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ], function (User $user, string $password) use (&$resetUser): void {
            $user->update([
                'password' => $password,
            ]);

            $resetUser = $user;
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ApiException::badRequest('Unable to reset password.');
        }

        if ($resetUser !== null) {
            $event = $this->notificationEventBuilder->build(eventType: 'PASSWORD_RESET', userId: (string) $resetUser->id, data: [
                'userId' => $resetUser->id,
                'email' => $resetUser->email,
            ], );

            $this->notificationEventEmitter->emit($event);
        }
    }
}
