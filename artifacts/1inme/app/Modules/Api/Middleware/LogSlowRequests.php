<?php

namespace App\Modules\Api\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight slow-request logger for the mobile API path.
 *
 * Logs every request that either exceeds THRESHOLD_MS or fires more than
 * QUERY_COUNT_THRESHOLD database queries. This makes performance regressions
 * visible without requiring an external APM tool.
 *
 * The query counter uses enableQueryLog()/getQueryLog() so it works in any
 * PHP-FPM environment without registering persistent listeners that would
 * accumulate across requests in long-lived processes.
 *
 * Log channel: the default Laravel log (storage/logs/laravel.log).
 * Log level: warning (so it surfaces above info noise but below errors).
 * Log key: api.slow_request
 */
class LogSlowRequests
{
    /** Minimum request duration in milliseconds before we log it. */
    private const THRESHOLD_MS = 800;

    /** Minimum DB query count per request before we log it. */
    private const QUERY_COUNT_THRESHOLD = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        DB::enableQueryLog();

        $response = $next($request);

        $durationMs  = (int) round((microtime(true) - $start) * 1000);
        $queryCount  = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        if ($durationMs >= self::THRESHOLD_MS || $queryCount >= self::QUERY_COUNT_THRESHOLD) {
            $route = $request->route()?->uri() ?? $request->path();
            Log::warning('api.slow_request', [
                'method'      => $request->method(),
                'route'       => $route,
                'path'        => $request->path(),
                'duration_ms' => $durationMs,
                'query_count' => $queryCount,
                'status'      => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
