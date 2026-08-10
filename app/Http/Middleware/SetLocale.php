<?php

namespace App\Http\Middleware;

use App\Support\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->route('lang');

        // The {lang} route segment is still matched against
        // LocaleRegistry::routePattern() at the routing layer, so a
        // deactivated code shouldn't normally reach here at all — this is
        // a second, defensive check for the route-cache window between
        // deactivating a language and the next route:cache/deploy.
        if ($lang && in_array($lang, LocaleRegistry::codes(), true)) {
            app()->setLocale($lang);
        }

        return $next($request);
    }
}
