<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::prefix('health')->group(function () {

    /**
     * Liveness Probe
     */
    Route::get('/', function () {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    /**
     * Readiness Probe
     */
    Route::get('/ready', function () {

        $checks = [
            'database' => 'unreachable',
            'redis' => 'unreachable',
        ];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable $e) {
        }

        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (Throwable $e) {
        }

        $healthy = collect($checks)
            ->every(fn ($status) => $status === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'unavailable',
            'service' => config('app.name'),
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    });

});
