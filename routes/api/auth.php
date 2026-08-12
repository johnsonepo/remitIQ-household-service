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
 * ============================================================================
 */
Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register'])
        ->name('auth.register');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login');

});
