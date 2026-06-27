<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Dashboard;
use App\Services\AdminNavService;
use Closure;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records each admin page visit into AdminNavService::recordVisit() for the
 * sidebar "Recently Viewed" list. Runs as panel middleware (not a Livewire
 * componentHook) because registering a global Livewire::componentHook() from
 * AppServiceProvider was confirmed NOT to fire — Livewire's
 * ComponentHookRegistry::boot() (which wires the actual mount listeners) has
 * already run by the time any app-level provider's register()/boot() executes
 * in this app. Middleware has no such ordering risk: every page visit,
 * including wire:navigate SPA transitions, is a real GET to the matched
 * Filament route, so this fires reliably and needs zero per-page edits.
 *
 * Derives everything from the route's bound controller class + route-model
 * binding (already resolved by SubstituteBindings before this middleware
 * runs) rather than the Livewire Page instance, since the Page hasn't
 * mounted yet at the middleware stage.
 */
class RecordsAdminPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('get') && $response->getStatusCode() === 200) {
            $this->recordVisit($request);
        }

        return $response;
    }

    protected function recordVisit(Request $request): void
    {
        $route = $request->route();

        if (! $route) {
            return;
        }

        $controller = $route->getAction('controller');

        if (! is_string($controller) || ! class_exists($controller) || ! is_subclass_of($controller, Page::class)) {
            return;
        }

        if ($controller === Dashboard::class || is_subclass_of($controller, CreateRecord::class)) {
            return;
        }

        $admin = auth('admin')->user();

        if (! $admin) {
            return;
        }

        // Full absolute URL (scheme + host + path, no query string) — matches
        // the convention SidebarPinnedNav/AdminNavService::pin() already use
        // for stored nav URLs (Filament's NavigationItem::getUrl() returns
        // absolute URLs, not host-relative paths).
        $url = $request->url();

        if (is_subclass_of($controller, ResourcePage::class)) {
            $resource = $controller::getResource();
            $recordKey = $route->parameter('record');
            $record = $recordKey ? $resource::resolveRecordRouteBinding($recordKey) : null;

            $label = $record
                ? (string) $resource::getRecordTitle($record)
                : (string) $resource::getNavigationLabel();
            $group = $resource::getNavigationGroup();
        } else {
            $label = (string) $controller::getNavigationLabel();
            $group = $controller::getNavigationGroup();
        }

        if ($group instanceof \UnitEnum) {
            $group = $group->name;
        }

        AdminNavService::recordVisit($admin, $url, $label, $url, $group);
    }
}
