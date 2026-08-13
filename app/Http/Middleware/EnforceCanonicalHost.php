<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Canonicalizes scheme/host/trailing-slash in ONE combined 301, before
 * routing even happens (registered on the application's global middleware
 * stack, not a route-scoped group) — so a request that wouldn't match any
 * route still gets redirected to its canonical form first, rather than
 * 404ing on the wrong host/scheme/slash.
 *
 * Previously this was handled inconsistently per deployment target:
 * Apache's public/.htaccess stripped trailing slashes, the nginx config
 * didn't, and nothing forced host/scheme anywhere. Doing it once here, in
 * the framework, gives one consistent behavior regardless of which web
 * server actually fronts a given environment.
 */
class EnforceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        // Registered on the GLOBAL middleware stack (not the 'web' group)
        // so it also canonicalizes requests that won't match any route —
        // but that means it also sees the health-check endpoint and the
        // API, which must never be redirected: load balancers/monitoring
        // often hit /up over plain HTTP via an internal hostname, and a
        // 301 there reads as "unhealthy," not "moved." Mirrors
        // HandleRedirects' own admin/api bypass convention.
        if ($request->is('up') || $request->is('api/*')) {
            return $next($request);
        }

        // Mirrors AppServiceProvider's existing URL::forceScheme('https')
        // gate: only force https when the app is actually configured to be
        // served over it. Forcing unconditionally would break a production
        // install accessed over plain HTTP before SSL is provisioned (e.g.
        // the shared-hosting web installer).
        $httpsConfigured = str_starts_with((string) config('app.url'), 'https://');

        // Empty by default (no admin UI for this yet — lands with the SEO
        // Control Center) — an empty value opts this check out entirely
        // rather than forcing every environment (local dev, staging) onto
        // a host they were never configured to use.
        $canonicalHost = trim((string) settings('seo.canonical_host', ''));

        $needsSchemeChange = $httpsConfigured && ! $request->secure();
        $needsHostChange = $canonicalHost !== '' && $request->getHost() !== $canonicalHost;

        // Trailing-slash removal is GET/HEAD only — a 301 on a POST/PUT/etc.
        // risks an older HTTP client dropping the request body when it
        // follows the redirect — and never strips the bare root path.
        $path = $request->getPathInfo();
        $canStripSlash = $request->isMethod('GET') || $request->isMethod('HEAD');
        $needsSlashStrip = $canStripSlash && $path !== '/' && str_ends_with($path, '/');

        if (! $needsSchemeChange && ! $needsHostChange && ! $needsSlashStrip) {
            return $next($request);
        }

        $scheme = $needsSchemeChange ? 'https' : $request->getScheme();
        // getHost() strips the port entirely — fine for $canonicalHost (an
        // admin-entered bare domain, never expected to include one), but
        // wrong when keeping the request's own host: it silently dropped
        // a non-standard port on every scheme/slash-only redirect (e.g.
        // Docker port-mapped local/staging setups), sending the browser to
        // the same host on the default port instead — found by actually
        // browsing a port-mapped rehearsal instance, not by code reading.
        // getHttpHost() includes the port only when it's non-default for
        // the scheme, so a normal production install on 80/443 is unaffected.
        $host = $needsHostChange ? $canonicalHost : $request->getHttpHost();
        $finalPath = $needsSlashStrip ? (rtrim($path, '/') ?: '/') : $path;
        $query = $request->getQueryString();

        $url = "{$scheme}://{$host}{$finalPath}" . ($query ? "?{$query}" : '');

        return redirect($url, 301);
    }
}
