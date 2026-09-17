<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Public health check endpoint for container and load balancer health monitoring.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'connected';
        $cacheStatus = 'working';
        $overallStatus = 'ok';

        // Check database connectivity
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbStatus = 'error';
            $overallStatus = 'error';
        }

        // Check cache connectivity
        try {
            $cacheKey = '_health_check_' . microtime(true);
            Cache::put($cacheKey, true, 10);
            if (Cache::get($cacheKey) !== true) {
                $cacheStatus = 'error';
                $overallStatus = 'error';
            } else {
                Cache::forget($cacheKey);
            }
        } catch (Throwable $e) {
            $cacheStatus = 'error';
            $overallStatus = 'error';
        }

        $statusCode = $overallStatus === 'ok' ? 200 : 503;

        return response()->json([
            'status' => $overallStatus,
            'database' => $dbStatus,
            'cache' => $cacheStatus,
            'timestamp' => now()->toIso8601String(),
        ], $statusCode);
    }
}
