<?php

namespace App\Http\Middleware;

use App\Services\Migration\Export\FreezeReleaser;
use App\Services\Migration\Export\FreezeState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * For a host with no cron and a synchronous queue, where nothing else could end an expired freeze: the
 * first visitor after the deadline does it. A shop nobody visits is not hurt by being frozen a little
 * longer; the first customer to arrive finds it open. On every other request this costs one missing-file
 * check.
 */
class ReleaseExpiredFreeze
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_file(FreezeState::file()->path())) {
            try {
                app(FreezeReleaser::class)->releaseIfExpired();
            } catch (\Throwable $e) {
                // A visitor must never be refused because lifting a freeze failed; the watchdog will try again. But it must be seen.
                Log::channel('migration')->error('Lifting an expired export freeze on a web request failed: '.$e->getMessage());
            }
        }

        return $next($request);
    }
}
