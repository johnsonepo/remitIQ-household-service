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
 *
 * Protected endpoints
 * -------------------
 * - Refresh token
 * - Logout
 * - Current profile
 * - Profile update
 * - Password change
 *
 * ============================================================================
 */
Route::prefix('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication
    |--------------------------------------------------------------------------
    |
    | These endpoints do not require an existing JWT.
    |
    */

    Route::post('/register', [AuthController::class, 'register'])
        ->name('auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login');

    /*
    |--------------------------------------------------------------------------
    | Protected Authentication
    |--------------------------------------------------------------------------
    |
    | All routes in this group require a valid JWT through the API guard.
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

    });

});