<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks in the CSP header content so a future edit can't silently drop a
 * required directive/domain without a test failing — nothing previously
 * asserted this at all (confirmed via grep before writing this file).
 * Airwallex domain grounding: app/Http/Middleware/ContentSecurityPolicy.php's
 * own comment block.
 *
 * The full live Airwallex sandbox run (real credentials, card element mount
 * -> confirm, watching DevTools for CSP violations) this test couldn't
 * replace has since happened — it found 3 more required domains beyond the
 * ones already here (o11y[-demo], checkout-demo, bws[-demo]), each of which
 * broke the payment step in a real, live-reproduced way when missing (see
 * the middleware's own comments for exactly how). Asserted here too now.
 */
class ContentSecurityPolicyTest extends TestCase
{
    #[Test]
    public function the_csp_header_is_present_on_a_normal_page_response(): void
    {
        $response = $this->get('/en/');

        $response->assertHeader('Content-Security-Policy');
    }

    #[Test]
    public function the_csp_header_allows_every_required_airwallex_domain(): void
    {
        $csp = $this->get('/en/')->headers->get('Content-Security-Policy');

        foreach ([
            'https://checkout.airwallex.com',
            'https://checkout-demo.airwallex.com',
            'https://static.airwallex.com',
            'https://static-demo.airwallex.com',
            'https://api.airwallex.com',
            'https://api-demo.airwallex.com',
            'https://pci-api.airwallex.com',
            'https://pci-api-demo.airwallex.com',
            'https://o11y.airwallex.com',
            'https://o11y-demo.airwallex.com',
            'https://bws.airwallex.com',
            'https://bws-demo.airwallex.com',
        ] as $domain) {
            $this->assertStringContainsString($domain, $csp, "CSP is missing required Airwallex domain: {$domain}");
        }

        // The SDK renders card-input / 3-D Secure iframes — frame-src 'none'
        // (the object-src/base-uri default-deny posture elsewhere in this
        // policy) would silently kill the payment step. checkout-demo (not
        // just checkout) is the one actually framed when
        // payment.airwallex_environment is 'sandbox' — confirmed live: this
        // was missing and blocked the card iframe outright.
        $this->assertMatchesRegularExpression(
            '/frame-src[^;]*checkout\.airwallex\.com/',
            $csp,
            'Airwallex must be explicitly allowed in frame-src for the card/3DS iframe to render.'
        );
        $this->assertMatchesRegularExpression(
            '/frame-src[^;]*checkout-demo\.airwallex\.com/',
            $csp,
            'checkout-demo.airwallex.com must be explicitly allowed in frame-src — the sandbox card iframe is framed from this origin, not checkout.airwallex.com.'
        );
    }

    #[Test]
    public function the_csp_header_still_locks_down_object_and_base_uri(): void
    {
        $csp = $this->get('/en/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    #[Test]
    public function the_other_required_security_headers_are_present(): void
    {
        $response = $this->get('/en/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        // payment=(self): Airwallex Apple Pay / Google Pay needs the Payment
        // Request API on our own origin; camera/mic/geolocation stay denied.
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
    }
}
