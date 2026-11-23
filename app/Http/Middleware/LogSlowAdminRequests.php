<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one log line for every admin-panel request (page loads, Livewire calls, navigation) that takes longer than
 * `app.slow_admin_log_seconds` (env SLOW_ADMIN_LOG_SECONDS, default 3; 0 switches it off). The line says where the time
 * went: total time, number of database queries, time spent in them, and the slowest query's text. Query values are never
 * logged. It is how a "the admin panel hangs on the first click" report is traced to its real cause on a live server.
 */
class LogSlowAdminRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $threshold = (float) config('app.slow_admin_log_seconds', 3);

        if ($threshold <= 0 || ! ($request->is('admin') || $request->is('admin/*') || $request->is('livewire/*'))) {
            return $next($request);
        }

        $started = microtime(true);
        $queries = 0;
        $queryMs = 0.0;
        $slowest = ['ms' => 0.0, 'sql' => ''];

        DB::listen(function ($query) use (&$queries, &$queryMs, &$slowest) {
            $queries++;
            $queryMs += $query->time;
            if ($query->time > $slowest['ms']) {
                $slowest = ['ms' => $query->time, 'sql' => $query->sql];
            }
        });

        $response = $next($request);

        $seconds = microtime(true) - $started;
        if ($seconds >= $threshold) {
            try {
                Log::warning('Slow admin request', [
                    'method' => $request->method(),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'status' => $response->getStatusCode(),
                    'seconds' => round($seconds, 2),
                    'queries' => $queries,
                    'query_seconds' => round($queryMs / 1000, 2),
                    'slowest_query_ms' => round($slowest['ms'], 1),
                    'slowest_query' => mb_substr($slowest['sql'], 0, 300),
                    'memory_mb' => round(memory_get_peak_usage(true) / 1048576),
                ]);
            } catch (\Throwable) {
                // A log line must never fail the page.
            }
        }

        return $response;
    }
}
