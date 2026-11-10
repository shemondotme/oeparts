<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Google (and Bing) retired their anonymous `/ping?sitemap=` URLs — calling
 * them is a no-op. A regenerated sitemap is now submitted to Google through
 * the authenticated Search Console API, and only when that integration is
 * configured. Generation must never fail because of it.
 */
class SitemapSearchConsoleSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []); // parallel test processes share this real folder
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function setSeo(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'seo', 'key' => $key],
                ['value' => $value, 'type' => str_ends_with($key, '_enabled') ? 'boolean' : 'string', 'is_encrypted' => false]
            );
        }
        app(SettingsService::class)->forget('seo');
    }

    private function configureSearchConsole(): void
    {
        $this->setSeo([
            'gsc_client_id' => 'cid',
            'gsc_client_secret' => 'secret',
            'gsc_refresh_token' => 'refresh',
            'gsc_property_url' => 'https://example.com/',
        ]);
    }

    #[Test]
    public function the_sitemap_is_submitted_to_search_console_when_configured_and_enabled(): void
    {
        $this->configureSearchConsole();
        $this->setSeo(['google_ping_enabled' => '1']);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'searchconsole.googleapis.com/*' => Http::response('', 204),
            '*' => Http::response('', 200),
        ]);

        app(SitemapService::class)->generateAll();

        Http::assertSent(fn ($r) => $r->method() === 'PUT'
            && str_contains($r->url(), 'searchconsole.googleapis.com/webmasters/v3/sites/')
            && str_contains($r->url(), '/sitemaps/')
            && str_contains(urldecode($r->url()), 'sitemap.xml'));
    }

    #[Test]
    public function the_retired_anonymous_ping_urls_are_never_called(): void
    {
        $this->configureSearchConsole();
        $this->setSeo(['google_ping_enabled' => '1']);
        Http::fake(['*' => Http::response('', 200)]);

        app(SitemapService::class)->generateAll();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'google.com/ping'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'bing.com/ping'));
    }

    #[Test]
    public function nothing_is_sent_when_search_console_is_not_configured(): void
    {
        $this->setSeo(['google_ping_enabled' => '1']);
        Http::fake();

        app(SitemapService::class)->generateAll();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'googleapis.com'));
    }

    #[Test]
    public function submission_is_skipped_when_the_setting_is_the_literal_string_false(): void
    {
        // An admin-saved boolean persists as the string 'false' (PHP-truthy):
        // only filter_var() reads it as disabled.
        $this->configureSearchConsole();
        $this->setSeo(['google_ping_enabled' => 'false']);
        Http::fake();

        app(SitemapService::class)->generateAll();

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'googleapis.com'));
    }

    #[Test]
    public function sitemap_generation_succeeds_even_if_search_console_is_unreachable(): void
    {
        $this->configureSearchConsole();
        $this->setSeo(['google_ping_enabled' => '1']);
        Http::fake(fn () => throw new ConnectionException('down'));

        $files = app(SitemapService::class)->generateAll();

        $this->assertNotEmpty($files, 'sitemap generation must succeed regardless of Search Console reachability');
    }
}
