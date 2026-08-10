<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * At ~100k products, every storefront page view is a full round-trip to the
 * app even when a guest just hit back/forward or reloaded the exact same
 * URL seconds later. CacheGuestStorefrontResponse adds a short `private,
 * max-age` Cache-Control header on an explicit allowlist of safe, guest-only
 * routes so the visitor's OWN browser can skip that round-trip — `private`
 * (never `public`) because the navbar/search-box forms embed a session-tied
 * CSRF token, which a shared proxy/CDN must never be allowed to cache and
 * replay to a different visitor.
 */
class CacheGuestStorefrontResponseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_gets_a_private_cache_control_header_on_the_homepage(): void
    {
        $response = $this->get('/en/');

        $response->assertOk();
        // Symfony's HeaderBag re-serializes Cache-Control directives in its
        // own (alphabetical) order regardless of how the middleware set them.
        $response->assertHeader('Cache-Control', 'max-age=60, private');
    }

    #[Test]
    public function authenticated_customer_never_gets_the_cache_header(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/en/');

        $response->assertOk();
        $this->assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function a_404_response_is_never_cached(): void
    {
        $response = $this->get('/en/this-page-does-not-exist-at-all');

        $response->assertNotFound();
        $this->assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function personalized_routes_are_not_on_the_cache_allowlist(): void
    {
        $response = $this->get('/en/cart');

        $response->assertOk();
        $this->assertStringNotContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }
}
