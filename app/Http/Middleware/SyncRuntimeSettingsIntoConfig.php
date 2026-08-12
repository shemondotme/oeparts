<?php

namespace App\Http\Middleware;

use App\Services\RuntimeSettingsSyncService;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;

/**
 * Re-applies DB-backed settings onto config() at the start of every
 * request — see RuntimeSettingsSyncService's docblock for why this can't
 * just run once at ServiceProvider::boot() time. Cheap: SettingsService
 * caches each group for 5 minutes, so this is an array read, not a query,
 * on all but the first request per cache window.
 */
class SyncRuntimeSettingsIntoConfig
{
    public function handle(Request $request, Closure $next)
    {
        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        return $next($request);
    }
}
