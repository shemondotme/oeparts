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
        // Airwallex Elements (checkout/payment) needs its own origins allowed:
        // it loads a browser SDK, calls Airwallex APIs from the browser, and
        // renders card-input / 3-D Secure iframes. Without these the payment step
        // is dead (script blocked, connect blocked, and frame-src 'none' blocks
        // the card iframe). Grounded in the integration (payment.blade.php loads
        // https://checkout.airwallex.com/...; PaymentService uses api[-demo].
        // airwallex.com). Only Airwallex origins are added — everything else stays
        // locked down. NOTE: reconcile with Airwallex's current published CSP list
        // and validate in their SANDBOX with real credentials before go-live —
        // this project has no Airwallex keys configured, so it cannot be exercised
        // end-to-end here. If embedded 3-D Secure fails, use redirect-based 3DS.
        $airwallexScript  = 'https://checkout.airwallex.com https://static.airwallex.com';
        $airwallexFrame   = 'https://checkout.airwallex.com https://static.airwallex.com';
        $airwallexConnect = 'https://checkout.airwallex.com https://static.airwallex.com '
            .'https://api.airwallex.com https://api-demo.airwallex.com '
            .'https://pci-api.airwallex.com https://pci-api-demo.airwallex.com';

        // integrations.* settings (GTM/GA4/Facebook Pixel — see layouts/app.blade.php)
        // load a browser script, call out to their own analytics endpoints, and
        // (GTM only) render a <noscript> fallback iframe. Grounded in each vendor's
        // documented snippet origins.
        $analyticsScript  = 'https://www.googletagmanager.com https://connect.facebook.net';
        $analyticsConnect = 'https://www.google-analytics.com https://analytics.google.com '
            .'https://www.googletagmanager.com https://www.facebook.com';
        $analyticsFrame   = 'https://www.googletagmanager.com';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com {$airwallexScript} {$analyticsScript}",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self' https://api.qrserver.com {$airwallexConnect} {$analyticsConnect}",
            "frame-src {$airwallexFrame} {$analyticsFrame}",
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
        // payment=(self): allow the Payment Request API for our own origin so
        // Airwallex Apple Pay / Google Pay can run on the checkout. camera/mic/
        // geolocation stay fully disabled.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');

        return $response;
    }
}
