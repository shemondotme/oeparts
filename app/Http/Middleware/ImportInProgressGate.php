<?php

namespace App\Http\Middleware;

use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStateStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an import is replacing the site, nothing else may answer. Registered FIRST in the global stack.
 *
 * With no import running this costs one is_file() (the same cost model as ResumeInterruptedUpdate) and does nothing.
 * With one running: the health check passes, the runner's own routes pass, and everything else (storefront, admin,
 * API, webhooks) gets a 503 from a page that reads nothing from the database: the database is being replaced, and the
 * settings the maintenance page would read may be half written. A stalled run says so on that page.
 *
 * It is NOT the maintenance mode: that one reads a setting from the table being replaced and lets the admin panel
 * through, which is exactly what must not happen here.
 */
class ImportInProgressGate
{
    public function __construct(private readonly ImportMarker $marker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->marker->isActive()) {
            return $next($request);
        }

        if ($request->is('up') || $request->is('migrate/run/*')) {
            return $next($request);
        }

        $stalled = $this->isStalled();

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $stalled
                    ? 'This site is being moved and the move needs attention. Please try again later.'
                    : 'This site is being moved to a new server. Please try again in a few minutes.',
                'errors' => null,
            ], 503)
                ->header('Retry-After', '120')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->view('errors.import-in-progress', ['stalled' => $stalled], 503)
            ->header('Retry-After', '120')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function isStalled(): bool
    {
        $runId = $this->marker->runId();

        if ($runId === null) {
            return false;
        }

        try {
            return is_file((new ImportStateStore)->directory($runId).DIRECTORY_SEPARATOR.'stalled.flag');
        } catch (\Throwable) {
            return false;
        }
    }
}
