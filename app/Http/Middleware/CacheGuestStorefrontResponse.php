<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a short-lived `Cache-Control: private, max-age=N` to safe, guest-only
 * storefront GET responses, so a visitor's own browser can skip a full
 * round-trip on back/forward navigation and quick repeat views — no server
 * work saved, but every such hit is one less request the app has to serve
 * at all.
 *
 * Deliberately `private`, never `public`: this app renders a session-tied
 * CSRF token into every page via the navbar/search-box forms, so a `public`
 * response risks a shared proxy/CDN caching one visitor's token and serving
 * it to another. `private` still lets the browser that made the request
 * cache it, which is where the actual win is for a same-visitor repeat view.
 *
 * Applied only to an explicit allowlist of routes in routes/web.php — never
 * blanket-applied to the whole {lang} group. Most of that group (account,
 * cart, checkout, contact, newsletter) is genuinely personalized or
 * state-changing and must never be cached.
 */
class CacheGuestStorefrontResponse
{
    public function handle(Request $request, Closure $next, int $seconds = 60): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')
            || auth('web')->check()
            || ! $response->isSuccessful()
        ) {
            return $response;
        }

        $response->headers->set('Cache-Control', "private, max-age={$seconds}");

        return $response;
    }
}
