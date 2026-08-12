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
 * pingGoogle()/pingBing() previously used a raw GuzzleHttp\Client directly,
 * unlike CloudflareService's use of the Http facade — untestable without a
 * real network call. Moved to Http::get() (same facade, same fakeability)
 * while adding the new Bing ping alongside the existing Google one. Also
 * fixed a pre-existing boolean-gotcha: google_ping_enabled was read without
 * filter_var(), so an admin-saved 'false' string (PHP-truthy) never
 * actually disabled the ping.
 */
class SitemapPingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map('unlink', glob(public_path('sitemaps/*.xml')) ?: []);
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function setPingSettings(bool $google, bool $bing): void
    {
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'google_ping_enabled'],
            ['value' => $google ? '1' : '0', 'type' => 'boolean', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'bing_ping_enabled'],
            ['value' => $bing ? '1' : '0', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');
    }

    #[Test]
    public function both_search_engines_are_pinged_when_enabled(): void
    {
        $this->setPingSettings(google: true, bing: true);
        Http::fake();

        app(SitemapService::class)->generateAll();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'google.com/ping'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'bing.com/ping'));
    }

    #[Test]
    public function bing_ping_is_skipped_when_disabled(): void
    {
        $this->setPingSettings(google: true, bing: false);
        Http::fake();

        app(SitemapService::class)->generateAll();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'google.com/ping'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'bing.com/ping'));
    }

    #[Test]
    public function google_ping_is_skipped_when_the_setting_is_the_literal_string_false(): void
    {
        // The exact bug this fix closes: an admin-saved boolean setting
        // persists as the string 'false', which is PHP-truthy on a bare
        // check — only filter_var() reads it correctly as disabled.
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'google_ping_enabled'],
            ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]
        );
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => 'bing_ping_enabled'],
            ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');
        Http::fake();

        app(SitemapService::class)->generateAll();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'google.com/ping'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'bing.com/ping'));
    }

    #[Test]
    public function sitemap_generation_succeeds_even_if_both_pings_are_unreachable(): void
    {
        $this->setPingSettings(google: true, bing: true);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        $files = app(SitemapService::class)->generateAll();

        $this->assertNotEmpty($files, 'sitemap generation must succeed regardless of ping reachability');
    }
}
