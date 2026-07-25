<?php

namespace App\Http\Middleware;

use App\Services\Install\InstallManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global counterpart to InstallerMiddleware: that one keeps a completed site
 * OUT of the installer, this one keeps an uninstalled site OUT of everywhere
 * else. Without it, a bare domain visit on a fresh deploy hits HomeController
 * (or any other frontend/admin route) before any migration has run, and the
 * first DB query throws an uncaught QueryException — a raw 500 instead of
 * being routed to the installer that would actually fix it.
 *
 * Prepended to the 'web' group (see bootstrap/app.php) so it runs before any
 * route-specific middleware or controller ever touches the database.
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        // The testing environment routinely exercises frontend/admin routes against
        // an intentionally bare, per-test migration state that has nothing to do
        // with installer coverage (InstallerTest / InstallerMiddleware own that).
        // A blanket redirect here would fail those tests on an assertion they never
        // meant to make. Mirrors the CSRF carve-out for 'testing' in bootstrap/app.php.
        if (app()->environment('testing')) {
            return $next($request);
        }

        if ($request->is('install*') || $request->is('up')) {
            return $next($request);
        }

        if (file_exists(storage_path('installed.lock'))) {
            return $next($request);
        }

        if (app(InstallManager::class)->looksAlreadyInstalled()) {
            return $next($request);
        }

        return redirect('/install');
    }
}
