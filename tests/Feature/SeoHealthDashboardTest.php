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
}
