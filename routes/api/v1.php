<?php

use App\Http\Controllers\Api\V1\Household\HouseholdController;
use App\Http\Controllers\Api\V1\Household\HouseholdInvitationController;
use App\Http\Controllers\Api\V1\Household\HouseholdMemberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * ============================================================================
 * Public / Health Check Endpoints
 * ============================================================================
 */
Route::get('/', function () {
    return response()->json([
        'service' => config('app.name'),
        'version' => 'v1',
        'status' => 'ok',
    ]);
});

Route::get('/debug', function (Request $request) {
    return response()->json([
        'ip' => $request->ip(),
        'user' => $request->user()?->id,
        'limiter_key' => $request->user()?->id ?: $request->ip(),
    ]);
});

/**
 * ============================================================================
 * Authentication
 * ============================================================================
 */

require __DIR__.'/auth.php';

/**
 * ============================================================================
 * Protected API Routes (Requires Authentication)
 * ============================================================================
 */
Route::middleware('auth:api')->group(function () {

    /**
     * ============================================================================
     * Households
     * ============================================================================
     */
    Route::apiResource('households', HouseholdController::class);

    /**
     * ============================================================================
     * Household Members
     * ============================================================================
     */
    Route::apiResource('households.members', HouseholdMemberController::class)
        ->except(['create', 'edit']);

    /**
     * ============================================================================
     * Household Invitations
     * ============================================================================
     */
    Route::apiResource('households.invitations', HouseholdInvitationController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::post('households/invitations/{token}/accept', [HouseholdInvitationController::class, 'accept'])
        ->name('households.invitations.accept');

    Route::post('households/invitations/{token}/decline', [HouseholdInvitationController::class, 'decline'])->name('households.invitations.decline');
});
