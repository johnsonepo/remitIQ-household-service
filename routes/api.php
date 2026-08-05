<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/**
 * Liveness check — confirms the application is up and responding,
 * with no dependency checks. Kept fast and simple.
 */
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

/**
 * Readiness check — confirms the service can actually serve requests
 * that depend on its infrastructure. Checks database and Redis
 * connectivity, returning 503 if either is unreachable, so load
 * balancers/orchestrators can route traffic away from an instance
 * that can't actually do its job.
 */
Route::get('/health/ready', function () {
    $checks = [
        'database' => 'unreachable',
        'redis' => 'unreachable',
    ];

    try {
        DB::select('SELECT 1');
        $checks['database'] = 'ok';
    } catch (\Throwable $e) {
        // Left as 'unreachable'
    }

    try {
        Redis::ping();
        $checks['redis'] = 'ok';
    } catch (\Throwable $e) {
        // Left as 'unreachable'
    }

    $allHealthy = collect($checks)->every(fn ($status) => $status === 'ok');

    return response()->json([
        'status' => $allHealthy ? 'ok' : 'unavailable',
        'service' => config('app.name'),
        'version' => '1.0.0',
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
    ], $allHealthy ? 200 : 503);
});
