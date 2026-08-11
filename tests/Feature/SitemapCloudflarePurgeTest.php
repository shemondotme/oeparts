<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * sitemap.xml and every sub-file live at a FIXED URL that gets overwritten
 * in place on every regeneration (unlike uploaded media/build assets, which
 * always get a new unique filename) — exactly the "same URL, content
 * changed" case an edge cache needs telling about. SitemapService::
 * generateAll() now purges those URLs from Cloudflare (best-effort,
 * silently no-ops when Cloudflare isn't configured).
 */
class SitemapCloudflarePurgeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Only remove the files this test writes — NOT the sitemaps/
        // directory itself. Other tests (e.g. SitemapProductsTest) call
        // SitemapService's private generate*Sitemap() methods directly via
        // reflection, bypassing ensureDirectory(), so they depend on this
        // directory already existing on disk.
        array_map('unlink', glob(public_path('sitemaps/*.xml')) ?: []);
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function enableCloudflare(): void
    {
        foreach ([
            'cloudflare_enabled' => ['true', 'boolean'],
            'cloudflare_zone_id' => ['zone123', 'string'],
            'cloudflare_api_token' => ['token456', 'encrypted'],
        ] as $key => [$value, $type]) {
            Setting::updateOrCreate(
                ['group' => 'performance', 'key' => $key],
                ['value' => $value, 'type' => $type, 'is_encrypted' => $type === 'encrypted']
            );
        }
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'google_ping_enabled'],
            ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('performance');
        app(SettingsService::class)->forget('seo');
    }

    #[Test]
    public function regenerating_the_sitemap_purges_it_from_cloudflare(): void
    {
        $this->enableCloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        app(SitemapService::class)->generateAll();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/purge_cache')) {
                return false;
            }

            $files = $request['files'] ?? [];

            return in_array(url('sitemap.xml'), $files, true)
                && collect($files)->contains(fn ($url) => str_contains($url, 'sitemap-parts'));
        });
    }

    #[Test]
    public function sitemap_generation_succeeds_even_if_cloudflare_is_unreachable(): void
    {
        $this->enableCloudflare();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        $files = app(SitemapService::class)->generateAll();

        $this->assertNotEmpty($files, 'sitemap generation must succeed regardless of Cloudflare');
    }

    #[Test]
    public function no_cloudflare_call_is_made_when_not_configured(): void
    {
        Http::fake();

        app(SitemapService::class)->generateAll();

        Http::assertNothingSent();
    }
}
