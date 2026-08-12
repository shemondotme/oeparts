<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Filament\Pages\Settings\SeoHealthDashboard;
use App\Filament\Widgets\Seo\ContentHealthWidget;
use App\Filament\Widgets\Seo\FeatureAdoptionWidget;
use App\Filament\Widgets\Seo\IndexNowActivityWidget;
use App\Filament\Widgets\Seo\InternalSearchAnalyticsWidget;
use App\Filament\Widgets\Seo\RedirectHealthWidget;
use App\Models\Admin;
use App\Models\FailedSearchLog;
use App\Models\IndexNowPushLog;
use App\Models\NotFoundLog;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SearchLog;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
    public function internal_search_analytics_widget_reports_the_match_type_ratio_and_zero_result_rate(): void
    {
        SearchLog::create(['search_query' => 'A1', 'normalized_query' => 'A1', 'result_count' => 1, 'match_type' => 'exact', 'lang' => 'en', 'ip_address' => '127.0.0.1']);
        SearchLog::create(['search_query' => 'A2', 'normalized_query' => 'A2', 'result_count' => 1, 'match_type' => 'cross_reference', 'lang' => 'en', 'ip_address' => '127.0.0.1']);
        FailedSearchLog::create(['search_query' => 'ZZZ', 'normalized_query' => 'ZZZ', 'lang' => 'en', 'ip_address' => '127.0.0.1', 'inquiry_submitted' => false]);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(InternalSearchAnalyticsWidget::class)
            ->assertSee('Searches (30d)')
            ->assertSee('Zero-Result Rate');
    }

    #[Test]
    public function content_health_widget_reports_translation_and_manual_description_coverage(): void
    {
        Product::factory()->withFullTranslations()->create();
        Product::factory()->create(); // en/de only — default ProductFactory shape

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(ContentHealthWidget::class)
            ->assertSee('Translation Coverage')
            ->assertSee('Manual Descriptions');
    }

    #[Test]
    public function feature_adoption_widget_reflects_the_detail_pages_toggle_and_own_image_percentage(): void
    {
        Setting::where('group', 'seo')->where('key', 'detail_pages_enabled')->update(['value' => 'true']);
        Product::factory()->withImages()->create();
        Product::factory()->create();

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(FeatureAdoptionWidget::class)
            ->assertSee('Enabled')
            ->assertSee('Own Product Images');
    }

    #[Test]
    public function index_now_activity_widget_reports_enabled_state_and_recent_failures(): void
    {
        Setting::where('group', 'seo')->where('key', 'indexnow_enabled')->update(['value' => 'true']);
        IndexNowPushLog::create(['url_count' => 3, 'status' => 'success']);
        IndexNowPushLog::create(['url_count' => 1, 'status' => 'failed', 'error_message' => 'timeout']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(IndexNowActivityWidget::class)
            ->assertSee('Enabled')
            ->assertSee('Last Push');
    }

    #[Test]
    public function redirect_health_widget_flags_a_looping_chain_and_counts_unresolved_404s(): void
    {
        Redirect::create(['from_url' => 'a', 'to_url' => 'b', 'type' => '301', 'is_active' => true]);
        Redirect::create(['from_url' => 'b', 'to_url' => 'a', 'type' => '301', 'is_active' => true]);
        NotFoundLog::recordHit('/dead-link', 'en', null, '127.0.0.1');

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(RedirectHealthWidget::class)
            ->assertSee('loop back on themselves', false)
            ->assertSee('Unresolved 404s');
    }
}
