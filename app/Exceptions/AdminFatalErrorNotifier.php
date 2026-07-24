<?php

namespace App\Exceptions;

use App\Jobs\NotifyAdminsOfFatalError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Emails super_admins the moment an uncaught exception reaches the top-level
 * handler while inside the admin panel, instead of leaving a silent white
 * screen with no signal anything broke. Wired from
 * bootstrap/app.php's withExceptions() reportable() hook, which Laravel only
 * calls for exceptions NOT in its own $internalDontReport list — validation
 * failures, 404/403/419, and auth redirects never reach here, only genuinely
 * unexpected errors (a broken widget query, a typo'd config key, …).
 *
 * Extracted into its own class (rather than living inline in bootstrap/
 * app.php) specifically so it's unit-testable without needing to trigger a
 * real HTTP 500 through the whole framework boot.
 */
class AdminFatalErrorNotifier
{
    private const THROTTLE_SECONDS = 3600;

    /** Never let error-reporting itself become a source of errors. */
    public function handle(\Throwable $e, ?Request $request): void
    {
        if (! $request || ! $request->is('admin/*')) {
            return;
        }

        try {
            if (! $this->claimThrottle($e, $request)) {
                return;
            }

            NotifyAdminsOfFatalError::dispatch([
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'occurred_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Swallow — an error while reporting an error must not itself throw.
        }
    }

    /**
     * At most one notification per distinct (exception + route) per hour —
     * a recurring bug must not spam admins on every single request.
     */
    private function claimThrottle(\Throwable $e, Request $request): bool
    {
        $key = 'admin_fatal_error.'.md5(get_class($e).'|'.$e->getMessage().'|'.$request->path());

        if (Cache::get($key)) {
            return false;
        }

        Cache::put($key, true, self::THROTTLE_SECONDS);

        return true;
    }
}
