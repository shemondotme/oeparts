<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth CSRF mitigation for the stateless guest-token-cookie
 * cart/checkout API (`/api/cart/*`, `/api/checkout/*`) — those routes sit
 * in the `api` middleware group (no CSRF token, by design: consumed by
 * both a genuine mobile app and, via the guest_token cookie, an
 * unauthenticated browser visitor) and identify a guest purely from a
 * plain cookie the browser attaches automatically. Modern browsers'
 * default SameSite=Lax cookie policy already blocks the practically
 * exploitable cross-site POST/fetch attack vector for this cookie
 * (confirmed empirically — see [[project_bulletproof_testing_2026_09]]
 * Phase 1) — this is an INDEPENDENT second layer, not a replacement for
 * it, closing the gap for older browsers that don't honor SameSite and
 * adding real defense-in-depth rather than resting entirely on one
 * mechanism for a revenue-critical flow.
 *
 * Rejects a state-changing request only when Origin/Referer is PRESENT
 * and does not match this request's own (already-canonicalized, see
 * EnforceCanonicalHost) host — never for an ABSENT header, since a
 * genuine native mobile HTTP client typically sends neither, so mobile
 * app compatibility is untouched. This mirrors the standard OWASP-
 * recommended "verify Origin" CSRF defense, deliberately NOT Sanctum's
 * stateful-CSRF mechanism: that only engages for requests whose Origin
 * already matches a trusted frontend domain, which is the OPPOSITE of
 * what a genuine cross-origin attacker's request looks like, and would
 * not have added any real protection for a plain custom cookie read
 * directly from the request rather than through Sanctum's own session
 * auth.
 */
class VerifySameOriginForStatefulCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('origin') ?: $request->headers->get('referer');

        if ($origin === null) {
            return $next($request);
        }

        $originHost = parse_url($origin, PHP_URL_HOST);

        if ($originHost !== null && $originHost !== $request->getHost()) {
            abort(403, 'Cross-origin request rejected.');
        }

        return $next($request);
    }
}
