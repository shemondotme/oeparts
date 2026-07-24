<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-triggered cron fallback: if the site's real system cron was never
 * configured (or stopped running), overdue scheduled tasks (backups,
 * sitemap, abandoned-cart emails, update checks, …) would otherwise just
 * silently never fire — the only signal today is an admin happening to
 * notice the dashboard's "Scheduler: stale" tile. This middleware triggers
 * `schedule:run` from a normal HTTP request's terminate() phase instead,
 * run in-process rather than a loopback HTTP request (simpler, no outbound
 * network call needed, matches rule #41's pure-PHP philosophy).
 *
 * Runs in terminate(), AFTER the response is already sent — under PHP-FPM
 * (the dominant shared-hosting SAPI), Symfony's Response::send() already
 * calls fastcgi_finish_request() before terminate() fires, so this adds
 * effectively zero perceived latency for whichever visitor's request
 * happened to trigger it. Without fastcgi_finish_request() (some non-FPM
 * SAPIs), the visitor's request is slightly slower on the rare occasions
 * this fires — an accepted trade-off for a fallback that only ever
 * activates when the real cron is already missing.
 *
 * Throttled to at most once every self::MIN_INTERVAL_SECONDS via a cache
 * lock, and only fires at all once the scheduler heartbeat (written every
 * minute by a real cron, App\Console\Commands\SchedulerHeartbeat) is missing
 * or stale — a host with real cron configured never triggers this at all.
 */
class TriggerDueScheduledTasks
{
    private const HEARTBEAT_KEY = 'scheduler_heartbeat';

    private const THROTTLE_KEY = 'scheduler.http_fallback_lock';

    private const STALE_AFTER_SECONDS = 150; // real cron heartbeats every ~60s

    private const MIN_INTERVAL_SECONDS = 60; // never more than once a minute

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->shouldTrigger()) {
            return;
        }

        // Claim the throttle window immediately — even if schedule:run itself
        // is slow, no other concurrent request should also fire it.
        Cache::put(self::THROTTLE_KEY, true, self::MIN_INTERVAL_SECONDS);

        try {
            Artisan::call('schedule:run');
        } catch (\Throwable $e) {
            Log::warning('HTTP cron fallback failed to run schedule:run: '.$e->getMessage());
        }
    }

    private function shouldTrigger(): bool
    {
        return $this->isSafeContext() && $this->isDue();
    }

    /**
     * Guards unrelated to timing — split out (protected, overridable) so
     * tests can exercise isDue()'s real cache/heartbeat logic without also
     * having to fake being outside the testing environment. The production
     * class is never subclassed; this split exists purely for testability.
     */
    protected function isSafeContext(): bool
    {
        if (! (bool) config('app.cron_fallback_enabled', true)) {
            return false;
        }

        // Purely an HTTP-request-triggered fallback — never fire during the
        // installer (pre-install DB/cache may not be ready) or in tests (this
        // middleware is global, so every HTTP test in the whole suite would
        // otherwise risk actually running schedule:run — real backups, real
        // emails — as a side effect of an unrelated test).
        if (app()->runningInConsole() || app()->environment('testing')) {
            return false;
        }

        return file_exists(storage_path('installed.lock'));
    }

    protected function isDue(): bool
    {
        try {
            if (Cache::get(self::THROTTLE_KEY)) {
                return false;
            }

            $heartbeat = Cache::get(self::HEARTBEAT_KEY);
            if (! $heartbeat) {
                return true; // never seen a heartbeat at all — worth a try
            }

            // abs(): Carbon 3's diffInSeconds() returns a SIGNED value (negative
            // when the argument is in the past) unlike Carbon 2's always-absolute
            // default — a stale (past) heartbeat must still compare as "> stale
            // threshold", not silently pass as "not stale" because it's negative.
            return abs(now()->diffInSeconds($heartbeat)) > self::STALE_AFTER_SECONDS;
        } catch (\Throwable) {
            return false; // cache store unreachable — don't compound the problem
        }
    }
}
