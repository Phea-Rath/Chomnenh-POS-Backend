<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiPerformanceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        DB::enableQueryLog();

        $start = microtime(true);

        $response = $next($request);

        $executionTime = round(
            (microtime(true) - $start) * 1000,
            2
        );

        Log::info('API Performance', [
            'url' => $request->path(),
            'method' => $request->method(),
            'status' => $response->status(),
            'response_time_ms' => $executionTime,
            'query_count' => count(DB::getQueryLog()),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        $response->headers->set(
            'X-Response-Time',
            "{$executionTime}ms"
        );

        return $response;

    }
}
