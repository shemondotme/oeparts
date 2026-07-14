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
        // airwallex.com). static-demo.airwallex.com is Airwallex's device-
        // fingerprinting script + risk-iframe.html, loaded automatically by the
        // SDK whenever env is 'demo' (i.e. every sandbox run, which is this
        // project's default payment.airwallex_environment) — without it here,
        // sandbox testing would silently CSP-block the fingerprint check the
        // moment real credentials are configured. Only Airwallex origins are
        // added — everything else stays locked down. NOTE: header + domain
        // presence is asserted by tests/Feature/ContentSecurityPolicyTest.php,
        // but a full live sandbox run (card element mount -> 3DS -> confirm,
        // watching DevTools for CSP violations) with real Airwallex sandbox
        // credentials still hasn't happened — this project has none configured.
        // If embedded 3-D Secure fails, use redirect-based 3DS.
        // checkout-demo.airwallex.com (distinct from checkout.airwallex.com) is
        // where the actual card-input iframe is framed FROM when
        // payment.airwallex_environment is 'sandbox' — confirmed live (real
        // sandbox card checkout): frame-src without it throws "Framing
        // 'https://checkout-demo.airwallex.com/' violates ... frame-src", and
        // the dropin element's createElement() call returns null as a result
        // (same failure mode as the o11y-demo connect-src gap below).
        $airwallexScript  = 'https://checkout.airwallex.com https://checkout-demo.airwallex.com https://static.airwallex.com https://static-demo.airwallex.com';
        $airwallexFrame   = 'https://checkout.airwallex.com https://checkout-demo.airwallex.com https://static.airwallex.com https://static-demo.airwallex.com';
        // o11y[-demo].airwallex.com is the Elements SDK's own telemetry beacon
        // (airtracker/logs) — confirmed via a real live sandbox card checkout
        // (Playwright, real Airwallex sandbox credentials) that blocking it
        // doesn't just drop a log line: Airwallex.createElement('dropin', ...)
        // returned null immediately afterward, so dropin.mount() threw
        // "Cannot read properties of null (reading 'mount')" and the card
        // element never rendered — a fully broken card-payment step. This is
        // exactly the live-verification gap the CSP work here previously
        // couldn't close (no sandbox credentials were configured at the time).
        // bws[-demo].airwallex.com ("browser web service") is a further
        // connect-src call the Elements SDK makes from the top-level page
        // during card-element session setup — confirmed live the same way
        // as o11y above (blocked -> console CSP violation on the real
        // sandbox run), separate from api[-demo]/pci-api[-demo].
        $airwallexConnect = 'https://checkout.airwallex.com https://static.airwallex.com https://static-demo.airwallex.com '
            .'https://api.airwallex.com https://api-demo.airwallex.com '
            .'https://pci-api.airwallex.com https://pci-api-demo.airwallex.com '
            .'https://o11y.airwallex.com https://o11y-demo.airwallex.com '
            .'https://bws.airwallex.com https://bws-demo.airwallex.com';

        // integrations.* settings (GTM/GA4/Facebook Pixel — see layouts/app.blade.php)
        // load a browser script, call out to their own analytics endpoints, and
        // (GTM only) render a <noscript> fallback iframe. Grounded in each vendor's
        // documented snippet origins.
        $analyticsScript  = 'https://www.googletagmanager.com https://connect.facebook.net';
        $analyticsConnect = 'https://www.google-analytics.com https://analytics.google.com '
            .'https://www.googletagmanager.com https://www.facebook.com';
        $analyticsFrame   = 'https://www.googletagmanager.com';

        // Crisp Chat (integrations.crisp_website_id) — the widget loads its own
        // script bundle, opens a real-time WebSocket relay, and self-hosts its
        // fonts; it does NOT use an iframe (injected as regular DOM). Origins
        // are grounded in Crisp's own published embed/CSP documentation but
        // NOT live-verified in this session (no browser tool available) —
        // this is the exact reason the master workflow deferred this
        // integration; validate in a real browser before go-live, same
        // caveat already carried for the Airwallex CSP block above.
        $crispScript  = 'https://client.crisp.chat';
        $crispConnect = 'https://client.crisp.chat wss://client.relay.crisp.chat';
        $crispFont    = 'https://client.crisp.chat';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com {$airwallexScript} {$analyticsScript} {$crispScript}",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net {$crispFont}",
            "connect-src 'self' https://api.qrserver.com {$airwallexConnect} {$analyticsConnect} {$crispConnect}",
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
