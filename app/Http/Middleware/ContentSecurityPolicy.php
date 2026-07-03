<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request nonce BEFORE rendering so csp_nonce() can read it
        // in Blade (bind request-scoped, not session — the nonce is per-response).
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        // IMPORTANT: do NOT add 'nonce-...' to script-src here. Per the CSP spec,
        // when a nonce (or hash) is present the browser IGNORES 'unsafe-inline',
        // which would block every inline <script> that lacks the exact nonce —
        // and this app renders many inline scripts (add-to-cart, checkout/payment,
        // toasts, settings-driven header/footer scripts). Alpine also requires
        // 'unsafe-eval', so a strict nonce-only CSP is not achievable regardless.
        // We therefore rely on 'unsafe-inline' + 'unsafe-eval' (as intended for
        // Filament/Livewire/Alpine). csp_nonce() still returns the per-request
        // nonce so nonce="" attributes remain valid and future-proof.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self' https://api.qrserver.com",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        return $response;
    }
}
