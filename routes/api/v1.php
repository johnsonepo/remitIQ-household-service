<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * API Version 1
 * ============================================================================
 *
 * All version 1 endpoints should be registered here.
 *
 * Example:
 *
 * Route::apiResource('households', HouseholdController::class);
 *
 * ============================================================================
 */

/**
 * ============================================================================
 * API Version Health
 * ============================================================================
 */
Route::get('/', function () {
    return response()->json([
        'service' => config('app.name'),
        'version' => 'v1',
        'status' => 'ok',
    ]);
});

/**
 * ============================================================================
 * Rate Limiter Debug
 * ============================================================================
 *
 * Development only.
 *
 * Remove before production.
 *
 * ============================================================================
 */
Route::get('/debug', function (Request $request) {

    return response()->json([
        'ip' => $request->ip(),
        'user' => $request->user()?->id,
        'limiter_key' => $request->user()?->id ?: $request->ip(),
    ]);

});
