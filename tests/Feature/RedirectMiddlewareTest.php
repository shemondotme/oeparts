<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HandleRedirects passed the RedirectType enum straight into
 * redirect($url, $status) — Symfony's RedirectResponse constructor requires
 * an int and doesn't coerce enums, so every configured redirect 500'd the
 * moment a visitor actually hit it. Cache::remember() also never cached the
 * (overwhelmingly common) "no redirect" case, since a cache miss and a
 * cached null are indistinguishable to remember().
 *
 * HandleRedirects is only registered on the {lang}-prefixed route group, so
 * $request->path() (and therefore from_url) always includes the locale
 * segment, e.g. "en/old-page" — not a bare "old-page".
 */
class RedirectMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_permanent_redirect_returns_301_instead_of_crashing(): void
    {
        Redirect::create([
            'from_url' => 'en/old-page', 'to_url' => '/en/new-page',
            'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0,
        ]);

        $response = $this->get('/en/old-page');

        $response->assertRedirect('/en/new-page');
        $response->assertStatus(301);
    }

    #[Test]
    public function a_temporary_redirect_returns_302(): void
    {
        Redirect::create([
            'from_url' => 'en/sale', 'to_url' => '/en/products/sale',
            'type' => RedirectType::Temporary, 'is_active' => true, 'hit_count' => 0,
        ]);

        $response = $this->get('/en/sale');

        $response->assertRedirect('/en/products/sale');
        $response->assertStatus(302);
    }

    #[Test]
    public function hit_count_increments_on_each_match(): void
    {
        $redirect = Redirect::create([
            'from_url' => 'en/old-page', 'to_url' => '/en/new-page',
            'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0,
        ]);

        $this->get('/en/old-page');
        $this->get('/en/old-page');

        $this->assertSame(2, $redirect->fresh()->hit_count);
    }

    #[Test]
    public function a_path_with_no_matching_redirect_is_cached_as_a_miss_not_left_uncached(): void
    {
        $this->get('/en/no-such-path');

        // Cache::remember() only short-circuits on a non-null value — before
        // the fix, a miss was never actually written to cache, so this key
        // would never exist regardless of the request above.
        $this->assertTrue(Cache::has('redirect.en/no-such-path'));
        $this->assertFalse(Cache::get('redirect.en/no-such-path'));
    }

    #[Test]
    public function editing_a_redirects_destination_takes_effect_immediately_not_after_the_cache_ttl(): void
    {
        $redirect = Redirect::create([
            'from_url' => 'en/old-page', 'to_url' => '/en/first-target',
            'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0,
        ]);

        $this->get('/en/old-page')->assertRedirect('/en/first-target');

        $redirect->update(['to_url' => '/en/second-target']);

        $this->get('/en/old-page')->assertRedirect('/en/second-target');
    }

    #[Test]
    public function a_path_cached_as_a_miss_starts_redirecting_immediately_once_a_redirect_is_created_for_it(): void
    {
        $this->get('/en/brand-new-path')->assertStatus(404);

        Redirect::create([
            'from_url' => 'en/brand-new-path', 'to_url' => '/en/target',
            'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0,
        ]);

        $this->get('/en/brand-new-path')->assertRedirect('/en/target');
    }

    #[Test]
    public function deleting_a_redirect_stops_it_from_firing_immediately(): void
    {
        $redirect = Redirect::create([
            'from_url' => 'en/old-page', 'to_url' => '/en/new-page',
            'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0,
        ]);

        $this->get('/en/old-page')->assertRedirect('/en/new-page');

        $redirect->delete();

        $this->get('/en/old-page')->assertStatus(404);
    }

    #[Test]
    public function a_redirect_matching_a_post_route_path_does_not_hijack_the_post_request(): void
    {
        // Wired onto the whole {lang} group, this middleware used to run for
        // every HTTP method — an admin-created redirect colliding with a
        // real POST route's path would silently 301 the submission instead
        // of letting the actual controller handle it. Exercised directly
        // against the middleware (bypassing the HTTP kernel/CSRF) to
        // isolate the method gate itself.
        Redirect::create([
            'from_url' => 'en/contact', 'to_url' => '/en/somewhere-else',
            'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0,
        ]);

        $request = \Illuminate\Http\Request::create('/en/contact', 'POST');
        $middleware = app(\App\Http\Middleware\HandleRedirects::class);

        $response = $middleware->handle($request, fn ($req) => new \Illuminate\Http\Response('handled', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('handled', $response->getContent());
    }
}
