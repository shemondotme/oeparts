<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Filament\Pages\Settings\SeoHealthDashboard;
use App\Models\Admin;
use App\Models\FailedSearchLog;
use App\Models\IndexNowPushLog;
use App\Models\NotFoundLog;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SearchLog;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SeoHealthDashboard used to be 7 separate StatsOverviewWidget classes,
 * each @livewire-included as its own visually disjoint block — consolidated
 * into one page with its own Blade view (op-tile-grid/op-card/op-status-pill/
 * op-health-row, the same vocabulary BackupDashboard/HealthCheckStats
 * already use) so this reads as one cohesive, modern dashboard instead.
 * These tests assert against the single page's rendered output rather than
 * per-widget Livewire components.
 */
class SeoHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    #[Test]
    public function seo_control_center_exposes_a_view_health_dashboard_header_action(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoControlCenter::class)
            ->assertActionExists('viewHealthDashboard');
    }

    #[Test]
    public function the_health_dashboard_page_is_reachable_by_a_super_admin(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $this->get(SeoHealthDashboard::getUrl())->assertSuccessful();
    }

    #[Test]
    public function a_non_admin_role_cannot_access_the_health_dashboard(): void
    {
        $support = Admin::factory()->create(['is_active' => true]);
        $support->assignRole('support');
        $this->actingAs($support, 'admin');

        $this->get(SeoHealthDashboard::getUrl())->assertForbidden();
    }

    #[Test]
    public function it_reports_the_match_type_ratio_and_zero_result_rate(): void
    {
        SearchLog::create(['search_query' => 'A1', 'normalized_query' => 'A1', 'result_count' => 1, 'match_type' => 'exact', 'lang' => 'en', 'ip_address' => '127.0.0.1']);
        SearchLog::create(['search_query' => 'A2', 'normalized_query' => 'A2', 'result_count' => 1, 'match_type' => 'cross_reference', 'lang' => 'en', 'ip_address' => '127.0.0.1']);
        FailedSearchLog::create(['search_query' => 'ZZZ', 'normalized_query' => 'ZZZ', 'lang' => 'en', 'ip_address' => '127.0.0.1', 'inquiry_submitted' => false]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Searches (30d)')
            ->assertSee('Zero-Result Rate')
            ->assertSee('Exact 50% · Cross-ref 50% · Partial 0%', false);
    }

    #[Test]
    public function it_reports_translation_and_manual_description_coverage(): void
    {
        Product::factory()->withFullTranslations()->create();
        Product::factory()->create(); // en/de only — default ProductFactory shape

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Translation Coverage')
            ->assertSee('Manual Descriptions')
            // Locale codes (DE/LT/FR/ES) are shown as a real flag icon (an
            // SVG asset — flag *emoji* render as plain two-letter codes on
            // Windows 10, defeating the point) + full country name.
            ->assertSee('Germany')
            ->assertSee('flags/de.svg', false);
    }

    #[Test]
    public function on_page_audit_reports_custom_title_and_description_coverage(): void
    {
        $withBoth = Product::factory()->create();
        SeoMeta::create(['metable_type' => Product::class, 'metable_id' => $withBoth->id, 'meta_title' => 'Custom Title', 'meta_description' => 'Custom description.']);
        Product::factory()->create(); // no seo_meta row at all

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('On-Page SEO Audit')
            ->assertSee('Custom Meta Titles')
            ->assertSee('1 / 2 products', false);
    }

    #[Test]
    public function on_page_audit_flags_products_sharing_an_identical_meta_title(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        SeoMeta::create(['metable_type' => Product::class, 'metable_id' => $a->id, 'meta_title' => 'Same Title']);
        SeoMeta::create(['metable_type' => Product::class, 'metable_id' => $b->id, 'meta_title' => 'Same Title']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Duplicate Meta Titles')
            ->assertSee('2 product(s)', false);
    }

    #[Test]
    public function on_page_audit_reports_image_alt_text_coverage(): void
    {
        $product = Product::factory()->create();
        \App\Models\ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/a.jpg', 'is_featured' => true, 'alt_text' => ['en' => 'Bosch brake pad, front, new']]);
        \App\Models\ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/b.jpg', 'alt_text' => null]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Image Alt Text')
            ->assertSee('1 / 2 images', false);
    }

    #[Test]
    public function on_page_audit_reports_structured_data_condition_mapping(): void
    {
        $unmapped = \App\Models\Condition::firstOrCreate(['slug' => 'salvage'], ['name' => 'Salvage', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        Product::factory()->create(['condition_id' => $unmapped->id]); // 'new' is the other, mapped, factory default

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Structured Data — Condition', false)
            ->assertSee('0 / 1 products', false);
    }

    #[Test]
    public function on_page_audit_flags_active_products_with_no_cross_references_or_car_models(): void
    {
        Product::factory()->create(); // thin — no relations attached

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Thin Catalog Entries')
            ->assertSee('1 product(s)', false);
    }

    #[Test]
    public function on_page_audit_does_not_flag_a_product_with_car_model_fitment_as_thin(): void
    {
        Product::factory()->withCarModels()->create();

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Thin Catalog Entries')
            ->assertSee('None', false);
    }

    #[Test]
    public function on_page_audit_flags_a_canonical_url_pointing_off_the_configured_domain(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'canonical_host'], ['value' => 'oeparts.com', 'type' => 'string', 'is_encrypted' => false]);
        $product = Product::factory()->create();
        SeoMeta::create(['metable_type' => Product::class, 'metable_id' => $product->id, 'canonical_url' => 'https://some-other-site.example/page']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Off-Domain Canonical URLs')
            ->assertSee('1 product(s)', false);
    }

    #[Test]
    public function search_performance_section_shows_not_connected_without_credentials(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Search Performance')
            ->assertSee('Connect Google Search Console');
    }

    #[Test]
    public function search_performance_section_shows_totals_and_top_queries_once_configured(): void
    {
        $service = app(SettingsService::class);
        $service->set('seo.gsc_client_id', 'client-123');
        $service->set('seo.gsc_client_secret', 'secret-abc');
        $service->set('seo.gsc_refresh_token', 'refresh-xyz');
        $service->set('seo.gsc_property_url', 'https://oeparts.test/');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*searchAnalytics/query*' => Http::sequence()
                ->push(['rows' => [['clicks' => 42, 'impressions' => 1000, 'ctr' => 0.042, 'position' => 8.3]]], 200)
                ->push(['rows' => [['keys' => ['bosch brake pad'], 'clicks' => 20, 'impressions' => 400, 'ctr' => 0.05, 'position' => 5.1]]], 200)
                ->push(['rows' => [['keys' => ['https://oeparts.test/en/parts/06L906036L'], 'clicks' => 20, 'impressions' => 400]]], 200),
        ]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Top Queries')
            ->assertSee('bosch brake pad');
    }

    #[Test]
    public function core_web_vitals_trend_is_hidden_with_fewer_than_two_snapshots(): void
    {
        app(SettingsService::class)->set('seo.crux_api_key', 'crux-key-123');

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([
                'record' => ['metrics' => ['largest_contentful_paint' => ['percentiles' => ['p75' => 1800]]]],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertDontSee('Week Trend', false);
    }

    #[Test]
    public function core_web_vitals_trend_shows_once_at_least_two_snapshots_exist(): void
    {
        app(SettingsService::class)->set('seo.crux_api_key', 'crux-key-123');

        \App\Models\CoreWebVitalsSnapshot::create(['lcp_ms' => 1800, 'lcp_rating' => 'good', 'recorded_at' => now()->subWeek()]);
        \App\Models\CoreWebVitalsSnapshot::create(['lcp_ms' => 2500, 'lcp_rating' => 'needs-improvement', 'recorded_at' => now()]);

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([
                'record' => ['metrics' => ['largest_contentful_paint' => ['percentiles' => ['p75' => 1800]]]],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Week Trend', false);
    }

    #[Test]
    public function it_reflects_the_detail_pages_toggle_and_own_image_percentage(): void
    {
        Setting::where('group', 'seo')->where('key', 'detail_pages_enabled')->update(['value' => 'true']);
        Product::factory()->withImages()->create();
        Product::factory()->create();

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Enabled')
            ->assertSee('Own Product Images');
    }

    #[Test]
    public function it_reports_index_now_enabled_state_and_recent_failures(): void
    {
        Setting::where('group', 'seo')->where('key', 'indexnow_enabled')->update(['value' => 'true']);
        IndexNowPushLog::create(['url_count' => 3, 'status' => 'success']);
        IndexNowPushLog::create(['url_count' => 1, 'status' => 'failed', 'error_message' => 'timeout']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('IndexNow')
            ->assertSee('1 failure(s)', false);
    }

    #[Test]
    public function it_flags_a_looping_redirect_chain_and_counts_unresolved_404s(): void
    {
        // A direct reverse pair is flagged from BOTH directions by
        // RedirectLoopDetector::findAllLoops() (each redirect's own chain
        // is walked independently) — 2 loop(s), not 1.
        Redirect::create(['from_url' => 'a', 'to_url' => 'b', 'type' => '301', 'is_active' => true]);
        Redirect::create(['from_url' => 'b', 'to_url' => 'a', 'type' => '301', 'is_active' => true]);
        NotFoundLog::recordHit('/dead-link', 'en', null, '127.0.0.1');

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('2 loop(s)', false)
            ->assertSee('Unresolved 404s');
    }

    #[Test]
    public function redirect_health_flags_a_target_pointing_at_a_nonexistent_oem(): void
    {
        Redirect::create(['from_url' => '/en/parts/oldnumber', 'to_url' => '/en/parts/DOESNOTEXIST', 'type' => '301', 'is_active' => true]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Broken Redirect Targets')
            ->assertSee('1 redirect(s)', false);
    }

    #[Test]
    public function redirect_health_does_not_flag_a_target_pointing_at_a_real_oem(): void
    {
        $product = Product::factory()->create(['oem_number' => '06L906036L', 'normalized_oem' => '06L906036L']);
        Redirect::create(['from_url' => '/en/parts/old-alias', 'to_url' => '/en/parts/'.$product->normalized_oem, 'type' => '301', 'is_active' => true]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Broken Redirect Targets')
            ->assertSee('None', false);
    }

    #[Test]
    public function redirect_health_does_not_flag_a_target_using_the_real_oems_human_formatted_dashed_style(): void
    {
        // The live route (NormalizeOemUrl/OemNormalizerService) strips ALL
        // non-alphanumerics before matching normalized_oem — a redirect
        // target in the human-formatted style visitors and admins actually
        // type (e.g. resolving a 404 via NotFoundLogResource) works fine
        // for real traffic but a bare strtoupper(trim()) comparison here
        // never matched, permanently miscounting it as broken.
        $product = Product::factory()->create(['oem_number' => '06L-906-036-L', 'normalized_oem' => '06L906036L']);
        Redirect::create(['from_url' => '/en/parts/old-alias', 'to_url' => '/en/parts/06L-906-036-L', 'type' => '301', 'is_active' => true]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Broken Redirect Targets')
            ->assertSee('None', false);
    }

    #[Test]
    public function not_found_trend_is_hidden_with_fewer_than_two_snapshots(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertDontSee('Week Unresolved-404 Trend', false);
    }

    #[Test]
    public function not_found_trend_shows_once_at_least_two_snapshots_exist(): void
    {
        \App\Models\NotFoundLogSnapshot::create(['unresolved_count' => 2, 'recorded_at' => now()->subWeek()]);
        \App\Models\NotFoundLogSnapshot::create(['unresolved_count' => 5, 'recorded_at' => now()]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Week Unresolved-404 Trend', false);
    }

    #[Test]
    public function google_search_console_section_shows_not_connected_without_credentials(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Google Search Console')
            ->assertSee('Not Connected');
    }

    #[Test]
    public function google_search_console_section_shows_indexed_and_submitted_counts_once_configured(): void
    {
        $service = app(SettingsService::class);
        $service->set('seo.gsc_client_id', 'client-123');
        $service->set('seo.gsc_client_secret', 'secret-abc');
        $service->set('seo.gsc_refresh_token', 'refresh-xyz');
        $service->set('seo.gsc_property_url', 'https://oeparts.test/');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            'searchconsole.googleapis.com/*' => Http::response([
                'sitemap' => [
                    ['errors' => 0, 'warnings' => 0, 'contents' => [['submitted' => 10, 'indexed' => 8]]],
                ],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Indexed vs Submitted')
            ->assertSee('8 / 10');
    }

    #[Test]
    public function core_web_vitals_section_shows_not_connected_without_an_api_key(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Core Web Vitals')
            ->assertSee('Not Connected');
    }

    #[Test]
    public function core_web_vitals_section_shows_ratings_once_configured(): void
    {
        app(SettingsService::class)->set('seo.crux_api_key', 'crux-key-123');

        Http::fake([
            'chromeuxreport.googleapis.com/*' => Http::response([
                'record' => [
                    'metrics' => [
                        'largest_contentful_paint' => ['percentiles' => ['p75' => 1800]],
                        'cumulative_layout_shift' => ['percentiles' => ['p75' => '0.05']],
                        'interaction_to_next_paint' => ['percentiles' => ['p75' => 150]],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('LCP (p75)')
            ->assertSee('Good');
    }

    #[Test]
    public function google_indexing_check_shows_not_connected_without_credentials(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Google Indexing Check')
            ->assertSee('Connect Google Search Console');
    }

    #[Test]
    public function google_indexing_check_reports_a_verdict_per_locale_once_configured(): void
    {
        $service = app(SettingsService::class);
        $service->set('seo.gsc_client_id', 'client-123');
        $service->set('seo.gsc_client_secret', 'secret-abc');
        $service->set('seo.gsc_refresh_token', 'refresh-xyz');
        $service->set('seo.gsc_property_url', 'https://oeparts.test/');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1'], 200),
            '*urlInspection/index:inspect*' => Http::response([
                'inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed']],
            ], 200),
        ]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SeoHealthDashboard::class)
            ->assertSee('Google Indexing Check')
            ->assertSee('Submitted and indexed')
            ->assertSee('Pass');
    }
}
