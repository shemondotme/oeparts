<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanonicalHostRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel's own $this->get()/call() test helpers normalize away a
     * trailing slash via prepareUrlForRequest() (`trim(url($uri), '/')`)
     * BEFORE the request is even built — genuinely testing this
     * middleware's trailing-slash behavior requires bypassing that and
     * dispatching a request built directly, the way call() does
     * internally minus the trim.
     */
    private function getWithRawUri(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $request = Request::create($uri, 'GET', [], [], [], $server);
        $response = $this->app->make(Kernel::class)->handle($request);

        return $this->createTestResponse($response, $request);
    }

    #[Test]
    public function plain_http_redirects_to_https_when_app_url_is_configured_for_https(): void
    {
        config(['app.url' => 'https://oeparts.com']);

        $response = $this->get('/en/parts');

        $response->assertStatus(301);
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }

    #[Test]
    public function no_https_redirect_when_app_url_is_not_configured_for_https(): void
    {
        // Matches AppServiceProvider's own URL::forceScheme('https') gate —
        // a shared-hosting install accessed over plain HTTP before SSL is
        // provisioned must not get redirect-looped.
        config(['app.url' => 'http://localhost']);

        $response = $this->get('/en/parts');

        $response->assertStatus(200);
    }

    #[Test]
    public function mismatched_host_redirects_to_the_configured_canonical_host(): void
    {
        config(['app.url' => 'http://localhost']); // isolate from the https check
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'canonical_host'],
            ['value' => 'oeparts.com', 'type' => 'string', 'is_encrypted' => false]
        );

        $response = $this->withHeaders(['Host' => 'www.oeparts.com'])->get('/en/parts');

        $response->assertStatus(301);
        $response->assertHeader('Location', 'http://oeparts.com/en/parts');
    }

    #[Test]
    public function no_host_redirect_when_canonical_host_setting_is_empty(): void
    {
        config(['app.url' => 'http://localhost']);

        // No seo.canonical_host row saved at all — must opt this check out
        // entirely rather than redirecting every unconfigured environment
        // (local dev, staging) onto some default host.
        $response = $this->withHeaders(['Host' => 'www.oeparts.test'])->get('/en/parts');

        $response->assertStatus(200);
    }

    #[Test]
    public function trailing_slash_is_stripped_via_301(): void
    {
        config(['app.url' => 'http://localhost']);

        $response = $this->getWithRawUri('http://localhost/en/parts/');

        $response->assertStatus(301);
        $response->assertHeader('Location', 'http://localhost/en/parts');
    }

    #[Test]
    public function bare_root_path_is_never_redirected_for_trailing_slash(): void
    {
        config(['app.url' => 'http://localhost']);

        $response = $this->getWithRawUri('http://localhost/');

        $this->assertNotSame(301, $response->getStatusCode());
    }

    #[Test]
    public function post_request_with_trailing_slash_is_not_redirected(): void
    {
        config(['app.url' => 'http://localhost']);

        // A 301 on a non-GET/HEAD request risks an older HTTP client
        // dropping the request body when it follows the redirect.
        $request = Request::create('http://localhost/en/contact/', 'POST');
        $response = $this->createTestResponse(
            $this->app->make(Kernel::class)->handle($request),
            $request
        );

        $this->assertNotSame(301, $response->getStatusCode());
    }

    #[Test]
    public function health_check_endpoint_is_never_redirected(): void
    {
        config(['app.url' => 'https://oeparts.com']);
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'canonical_host'],
            ['value' => 'oeparts.com', 'type' => 'string', 'is_encrypted' => false]
        );

        // Load balancers/monitoring often hit /up over plain HTTP via an
        // internal hostname — a 301 there would read as "unhealthy."
        $response = $this->withHeaders(['Host' => 'internal-lb.local'])->get('/up');

        $this->assertNotSame(301, $response->getStatusCode());
    }

    /**
     * Found by actually browsing a Docker port-mapped rehearsal instance
     * (localhost:8080), not by reading code: the trailing-slash strip
     * redirected to http://localhost/en instead of http://localhost:8080/en,
     * because getHost() unconditionally drops the port. getHttpHost() only
     * omits it when it's the scheme's default (80 for http, 443 for
     * https), so a normal production install on 80/443 sees no change —
     * this only matters for non-standard-port setups (Docker port mapping,
     * some staging environments).
     */
    #[Test]
    public function trailing_slash_strip_preserves_a_non_standard_port(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $response = $this->getWithRawUri('http://localhost:8080/en/parts/', ['Host' => 'localhost:8080']);

        $response->assertStatus(301);
        $response->assertHeader('Location', 'http://localhost:8080/en/parts');
    }

    #[Test]
    public function canonical_host_redirect_still_uses_the_bare_configured_host_not_the_requests_port(): void
    {
        config(['app.url' => 'http://localhost']);
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'canonical_host'],
            ['value' => 'oeparts.com', 'type' => 'string', 'is_encrypted' => false]
        );

        // An admin-configured canonical host is never expected to carry a
        // port — this must redirect to the bare configured host regardless
        // of what port the mismatched request itself arrived on.
        $response = $this->withHeaders(['Host' => 'www.oeparts.com:8080'])->get('/en/parts');

        $response->assertStatus(301);
        $response->assertHeader('Location', 'http://oeparts.com/en/parts');
    }

    #[Test]
    public function scheme_and_host_and_slash_are_combined_into_one_redirect_not_a_chain(): void
    {
        config(['app.url' => 'https://oeparts.com']);
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'canonical_host'],
            ['value' => 'oeparts.com', 'type' => 'string', 'is_encrypted' => false]
        );

        $response = $this->getWithRawUri('http://www.oeparts.com/en/parts/', ['Host' => 'www.oeparts.com']);

        $response->assertStatus(301);
        $response->assertHeader('Location', 'https://oeparts.com/en/parts');
    }
}
