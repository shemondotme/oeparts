<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
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
    private function getWithRawUri(string $uri, array $headers = []): TestResponse
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
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
    public function a_request_whose_tls_a_proxy_already_terminated_is_not_redirected_in_a_loop(): void
    {
        // Cloudflare "Flexible" SSL / a TLS-terminating load balancer: the visitor IS on
        // https, but the proxy talks plain http to the app, so $request->secure() is
        // false (proxies are not trusted). Forcing https here 301s every request to
        // itself forever — the whole site down right after an upgrade. Found while
        // rehearsing the 1.0.16 -> 2.0.0 update: TRUSTED_PROXIES from .env is not
        // applied at bootstrap, so relying on $request->secure() is not enough.
        config(['app.url' => 'https://oeparts.com']);

        $this->getWithRawUri('http://oeparts.com/en/parts', ['X-Forwarded-Proto' => 'https'])->assertStatus(200);
        $this->getWithRawUri('http://oeparts.com/en/parts', ['X-Forwarded-Proto' => 'https, http'])->assertStatus(200);
        $this->getWithRawUri('http://oeparts.com/en/parts', ['CF-Visitor' => '{"scheme":"https"}'])->assertStatus(200);
    }

    #[Test]
    public function a_forwarded_http_scheme_still_redirects_to_https(): void
    {
        config(['app.url' => 'https://oeparts.com']);

        $response = $this->getWithRawUri('http://oeparts.com/en/parts', ['X-Forwarded-Proto' => 'http']);

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

    private function setCanonicalHost(string $host = 'old-site.example'): void
    {
        config(['app.url' => 'http://localhost']);
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'canonical_host'],
            ['value' => $host, 'type' => 'string', 'is_encrypted' => false]
        );
    }

    /**
     * A restored/copied site carries the SOURCE site's canonical host. The admin
     * who has to change it can only reach the NEW domain, so the admin panel, the
     * Livewire endpoint it posts to, and the web installer must never be sent to
     * the old host.
     */
    #[Test]
    public function the_admin_panel_the_livewire_endpoint_and_the_installer_are_never_host_redirected(): void
    {
        $this->setCanonicalHost('old-site.example');

        foreach (['/admin', '/admin/login', '/livewire/update', '/install'] as $path) {
            $response = $this->withHeaders(['Host' => 'new-site.example'])->get($path);

            $this->assertNotSame(301, $response->getStatusCode(), "{$path} must not be redirected to the canonical host");
        }

        // The public storefront on the same foreign host IS still sent to the canonical host.
        $this->withHeaders(['Host' => 'new-site.example'])->get('/en/parts')
            ->assertStatus(301)
            ->assertHeader('Location', 'http://old-site.example/en/parts');
    }

    #[Test]
    public function the_scheme_redirect_still_applies_to_the_admin_panel(): void
    {
        config(['app.url' => 'https://oeparts.com']);

        $response = $this->getWithRawUri('http://oeparts.com/admin/login');

        $response->assertStatus(301);
        $this->assertStringStartsWith('https://oeparts.com/admin/login', $response->headers->get('Location'));
    }

    #[Test]
    public function the_import_runner_path_answers_before_any_setting_is_read(): void
    {
        $this->setCanonicalHost('old-site.example');

        // While a site import is half-restored (or the DB is empty) the settings table cannot be trusted.
        // Record the read instead of failing inside the mock: the framework's exception
        // handler would swallow an assertion thrown from inside a request.
        $canonicalHostWasRead = false;
        $settings = \Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->andReturnUsing(function (string $key) use (&$canonicalHostWasRead) {
            if ($key === 'seo.canonical_host') {
                $canonicalHostWasRead = true;
            }

            return null;
        });
        $this->app->instance(SettingsService::class, $settings);

        $response = $this->withHeaders(['Host' => 'new-site.example'])->get('/migrate/run/abc');

        $this->assertFalse($canonicalHostWasRead, 'migrate/run/* must be answered before seo.canonical_host is read');
        $this->assertNotSame(301, $response->getStatusCode());
    }

    #[Test]
    public function the_canonical_host_field_accepts_a_bare_host_and_rejects_a_url(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SettingsSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        foreach (['https://oeparts.com', 'oeparts.com/en', 'oeparts.com:8080', 'two words.com'] as $bad) {
            Livewire::test(SeoControlCenter::class)
                ->set('data.canonical_host', $bad)
                ->call('save')
                ->assertHasErrors(['data.canonical_host']);
        }

        Livewire::test(SeoControlCenter::class)
            ->set('data.canonical_host', 'www.oeparts.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('www.oeparts.com', Setting::where('group', 'seo')->where('key', 'canonical_host')->value('value'));
    }
}
