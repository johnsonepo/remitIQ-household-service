<?php

use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * API Routes
 * ============================================================================
 *
 * Entry point for all API routes.
 *
 * Route files are organized by concern and API version to keep the
 * application modular and future-proof.
 *
 * Current Version
 * ---------------
 * v1
 *
 * Future
 * ------
 * - v2
 * - v3
 *
 * ============================================================================
 */

require __DIR__.'/api/health.php';

Route::prefix('v1')
    ->middleware('throttle:api')
    ->group(base_path('routes/api/v1.php'));
