<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Setting;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for the SEO-module audit fixes: sitemap host pinning,
 * detail URLs in the sitemap, trailing-slash-free home URLs, one consistent
 * hreflang/x-default source, case-insensitive canonical host, redirect
 * query-string/chain/locale-less handling and standalone JSON-LD validity.
 */
class SeoHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []); // parallel runs share this real folder
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function setting(string $group, string $key, string $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type, 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget($group);
    }

    private function product(string $oem, string $name = 'Brake pad'): Product
    {
        $manufacturer = Manufacturer::firstOrCreate(['slug' => 'mfr'], ['name' => ['en' => 'Mfr'], 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        return Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => $oem, 'normalized_oem' => $oem,
            'name' => ['en' => $name], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
    }

    // ── Sitemap ──────────────────────────────────────────────────────────

    #[Test]
    public function the_sitemap_uses_the_saved_site_address_not_a_localhost_app_url(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->setting('general', 'site_url', 'https://live.example.org');
        $this->product('AAA111');

        app(SitemapService::class)->generateAll();

        $index = file_get_contents(public_path('sitemap.xml'));
        $parts = file_get_contents(public_path('sitemaps/sitemap-parts.xml'));
        $this->assertStringContainsString('https://live.example.org/sitemaps/sitemap-parts.xml', $index);
        $this->assertStringContainsString('https://live.example.org/en/parts/AAA111', $parts);
        $this->assertStringNotContainsString('localhost', $index.$parts);
    }

    #[Test]
    public function a_production_instance_refuses_to_publish_a_sitemap_of_local_urls(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no public site address');

        app(SitemapService::class)->generateAll();
    }

    #[Test]
    public function a_rejected_run_does_not_leave_the_generation_lock_held(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->app['env'] = 'production';

        try {
            app(SitemapService::class)->generateAll();
        } catch (\RuntimeException) {
        }

        $lock = Cache::lock('sitemap:generate:lock', 5);
        $this->assertTrue($lock->get(), 'the lock must be free after a rejected run');
        $lock->release();
    }

    #[Test]
    public function with_detail_pages_on_a_single_product_oem_lists_its_detail_url_not_the_redirecting_hub(): void
    {
        $this->setting('seo', 'detail_pages_enabled', '1', 'boolean');
        $single = $this->product('SOLO1', 'Solo part');
        $this->product('TWIN1', 'Twin A');
        $this->product('TWIN1', 'Twin B');

        app(SitemapService::class)->generateAll();
        $xml = file_get_contents(public_path('sitemaps/sitemap-parts.xml'));

        $this->assertStringContainsString("/en/parts/SOLO1/{$single->id}-", $xml);
        $this->assertStringNotContainsString('/en/parts/SOLO1</loc>', $xml);
        $this->assertStringContainsString('/en/parts/TWIN1</loc>', $xml, 'a multi-product OEM stays on the hub (no redirect)');
    }

    #[Test]
    public function homepage_urls_in_the_sitemap_have_no_trailing_slash(): void
    {
        app(SitemapService::class)->generateAll();
        $xml = file_get_contents(public_path('sitemaps/sitemap-pages.xml'));

        $this->assertMatchesRegularExpression('#<loc>http[^<]*/en</loc>#', $xml);
        $this->assertDoesNotMatchRegularExpression('#<loc>http[^<]*/en/</loc>#', $xml);
    }

    // ── Home URL / redirect chain ─────────────────────────────────────────

    #[Test]
    public function the_root_redirects_straight_to_the_slashless_locale_home(): void
    {
        $location = $this->get('/')->headers->get('Location');

        $this->assertMatchesRegularExpression('#^https?://[^/]+/[a-z]{2}$#', $location);
    }

    #[Test]
    public function the_home_page_canonical_has_no_trailing_slash_and_matches_a_real_200(): void
    {
        $response = $this->get('/en');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="'.url('/en').'">', false);
    }

    // ── hreflang ──────────────────────────────────────────────────────────

    #[Test]
    public function hreflang_tags_cover_the_registry_locales_and_x_default_is_the_same_everywhere(): void
    {
        $seo = app(SeoService::class);
        $html = $seo->hreflangTags(fn ($l) => "https://x.test/{$l}/about");

        foreach (['en', 'de', 'lt', 'fr', 'es'] as $code) {
            $this->assertStringContainsString("hreflang=\"{$code}\" href=\"https://x.test/{$code}/about\"", $html);
        }
        $this->assertStringContainsString('hreflang="x-default" href="https://x.test/en/about"', $html);
    }

    #[Test]
    public function the_home_page_x_default_is_the_default_locale_even_when_viewing_another_locale(): void
    {
        $this->get('/fr')->assertSee('hreflang="x-default" href="'.url('/en').'"', false);
    }

    // ── Canonical host ────────────────────────────────────────────────────

    #[Test]
    public function a_mixed_case_canonical_host_does_not_redirect_a_request_to_itself(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->setting('seo', 'canonical_host', 'OeParts.Com');

        // Built directly (not via $this->get()) so the request really arrives on oeparts.com.
        $request = \Illuminate\Http\Request::create('http://oeparts.com/en/parts', 'GET');
        $response = $this->createTestResponse($this->app->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request), $request);

        $response->assertStatus(200);
    }

    #[Test]
    public function a_canonical_host_saved_with_a_scheme_is_still_compared_by_host_only(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->setting('seo', 'canonical_host', 'https://oeparts.com/');

        $request = \Illuminate\Http\Request::create('http://oeparts.com/en/parts', 'GET');
        $response = $this->createTestResponse($this->app->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request), $request);

        $response->assertStatus(200);
    }

    // ── Redirects ─────────────────────────────────────────────────────────

    #[Test]
    public function a_redirect_keeps_the_visitors_query_string(): void
    {
        Redirect::create(['from_url' => 'en/old', 'to_url' => '/en/new', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);

        $this->get('/en/old?utm_source=mail&x=1')->assertStatus(301)->assertRedirect('/en/new?utm_source=mail&x=1');
    }

    #[Test]
    public function a_destination_with_its_own_query_string_wins(): void
    {
        Redirect::create(['from_url' => 'en/old', 'to_url' => '/en/new?ref=a', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);

        $this->get('/en/old?utm=z')->assertRedirect('/en/new?ref=a');
    }

    #[Test]
    public function a_chain_of_redirects_is_collapsed_into_one_hop(): void
    {
        Redirect::create(['from_url' => 'en/a', 'to_url' => '/en/b', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);
        Redirect::create(['from_url' => 'en/b', 'to_url' => '/en/c', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);

        $this->get('/en/a')->assertStatus(301)->assertRedirect('/en/c');
    }

    #[Test]
    public function a_redirect_loop_does_not_hang_or_crash(): void
    {
        Redirect::create(['from_url' => 'en/a', 'to_url' => '/en/b', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);
        Redirect::create(['from_url' => 'en/b', 'to_url' => '/en/a', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);

        $this->get('/en/a')->assertStatus(301);
    }

    #[Test]
    public function a_locale_less_legacy_url_with_a_saved_redirect_no_longer_404s(): void
    {
        Redirect::create(['from_url' => 'old-shop/brakes', 'to_url' => '/en/parts', 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);

        $this->get('/old-shop/brakes')->assertStatus(301)->assertRedirect('/en/parts');
    }

    #[Test]
    public function an_unknown_url_without_a_redirect_still_404s(): void
    {
        $this->get('/definitely-not-a-page-xyz')->assertStatus(404);
    }

    // ── JSON-LD ───────────────────────────────────────────────────────────

    #[Test]
    public function a_standalone_organization_block_carries_its_own_context(): void
    {
        $html = app(SeoService::class)->jsonLd('organization');
        $data = json_decode(strip_tags($html), true);

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Organization', $data['@type']);
    }

    #[Test]
    public function the_product_offer_points_at_the_detail_page_when_detail_pages_are_on(): void
    {
        $this->setting('seo', 'detail_pages_enabled', '1', 'boolean');
        $product = $this->product('JSONLD1')->load(['manufacturer', 'crossReferences', 'images', 'condition']);

        $data = json_decode(strip_tags(app(SeoService::class)->jsonLd('product', $product)), true);

        $this->assertStringContainsString("/parts/JSONLD1/{$product->id}-", $data['offers']['url']);
        $this->assertSame('10.00', $data['offers']['price']);
    }
}
