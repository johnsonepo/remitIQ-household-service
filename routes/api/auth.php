<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * Authentication Routes
 * ============================================================================
 *
 * Authentication endpoints for API version 1.
 *
 * Public endpoints
 * ----------------
 * - Register
 * - Login
 * - Email verification
 *
 * Protected endpoints
 * -------------------
 * - Refresh token
 * - Logout
 * - Current profile
 * - Profile update
 * - Password change
 * - Resend verification email
 *
 * ============================================================================
 */
Route::prefix('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/register', [AuthController::class, 'register'])
        ->name('auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login');

    Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])
        ->name('auth.password.forgot');

    Route::post('/password/reset', [AuthController::class, 'resetPassword'])
        ->name('auth.password.reset');

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    |
    | The frontend receives the verification link by email, extracts the
    | user ID and verification hash, then sends them to this API endpoint.
    |
    */

    Route::post('/email/verify', [AuthController::class, 'verifyEmail'])
        ->name('auth.email.verify');

    /*
    |--------------------------------------------------------------------------
    | Protected Authentication
    |--------------------------------------------------------------------------
    |
    | These routes require a valid JWT through the API guard.
    |
    */

    Route::middleware('auth:api')->group(function () {

        Route::post('/refresh', [AuthController::class, 'refresh'])
            ->name('auth.refresh');

        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        Route::get('/me', [AuthController::class, 'me'])
            ->name('auth.me');

        Route::patch('/profile', [AuthController::class, 'updateProfile'])
            ->name('auth.profile.update');

        Route::put('/profile/password', [AuthController::class, 'changePassword'])
            ->name('auth.profile.password');

        Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
            ->name('auth.email.resend');

    });

});
