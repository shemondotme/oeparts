<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks in the CSP header content so a future edit can't silently drop a
 * required directive/domain without a test failing — nothing previously
 * asserted this at all (confirmed via grep before writing this file).
 * Airwallex domain grounding: app/Http/Middleware/ContentSecurityPolicy.php's
 * own comment block. A full live Airwallex sandbox run (real credentials,
 * card element mount -> 3DS -> confirm, watching DevTools for CSP
 * violations) is a separate, manual go-live step this test cannot replace.
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
            'https://static.airwallex.com',
            'https://static-demo.airwallex.com',
            'https://api.airwallex.com',
            'https://api-demo.airwallex.com',
            'https://pci-api.airwallex.com',
            'https://pci-api-demo.airwallex.com',
        ] as $domain) {
            $this->assertStringContainsString($domain, $csp, "CSP is missing required Airwallex domain: {$domain}");
        }

        // The SDK renders card-input / 3-D Secure iframes — frame-src 'none'
        // (the object-src/base-uri default-deny posture elsewhere in this
        // policy) would silently kill the payment step.
        $this->assertMatchesRegularExpression(
            '/frame-src[^;]*checkout\.airwallex\.com/',
            $csp,
            'Airwallex must be explicitly allowed in frame-src for the card/3DS iframe to render.'
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
