<?php

namespace Tests\Feature;

use App\Filament\Clusters\Reports;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\FailedSearchLogResource;
use App\Filament\Resources\ManufacturerResource;
use App\Filament\Resources\NewsletterSubscriberResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PartInquiryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\RefundRequestResource;
use App\Filament\Support\AdminUi;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrilldownCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
            \Database\Seeders\SequencesSeeder::class,
            \Database\Seeders\CarriersSeeder::class,
            \Database\Seeders\SectionsSeeder::class,
        ]);

        $this->admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($this->admin, 'admin');
    }

    // ── RecentOrdersList Widget ────────────────────────────────────────────

    #[Test]
    public function recent_orders_list_drilldown_links_to_order_view(): void
    {
        $product = Product::factory()->create();
        $order = Order::factory()->create();

        $url = OrderResource::getUrl('view', ['record' => $order]);

        $this->assertStringContainsString('/admin/orders/' . $order->getKey(), $url);
    }

    // ── TopSearchedOems Widget ─────────────────────────────────────────────

    #[Test]
    public function top_searched_oems_drilldown_links_to_products_with_search(): void
    {
        $searchQuery = '06L906036L';

        $url = ProductResource::getUrl('index', ['tableSearch' => $searchQuery]);

        $this->assertStringContainsString('/admin/products', $url);
        $this->assertStringContainsString('tableSearch=06L906036L', $url);
    }

    // ── DashboardAlerts Widget ─────────────────────────────────────────────

    #[Test]
    public function dashboard_alerts_orders_pending_drilldown(): void
    {
        $url = OrderResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'pending']]]);

        $this->assertStringContainsString('/admin/orders', $url);
        $this->assertStringContainsString('tableFilters', $url);
        $this->assertStringContainsString('status', $url);
        $this->assertStringContainsString('pending', $url);
    }

    #[Test]
    public function dashboard_alerts_refund_requests_drilldown(): void
    {
        $url = RefundRequestResource::getUrl('index');

        $this->assertStringContainsString('/admin/refund-requests', $url);
    }

    #[Test]
    public function dashboard_alerts_contact_messages_drilldown(): void
    {
        $url = ContactMessageResource::getUrl('index');

        $this->assertStringContainsString('/admin/contact-messages', $url);
    }

    #[Test]
    public function dashboard_alerts_part_inquiries_drilldown(): void
    {
        $url = PartInquiryResource::getUrl('index');

        $this->assertStringContainsString('/admin/part-inquiries', $url);
    }

    // ── PartsInquiryWidget ─────────────────────────────────────────────────

    #[Test]
    public function parts_inquiry_widget_today_drilldown_links_with_status_filter(): void
    {
        $url = PartInquiryResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'new']]]);

        $this->assertStringContainsString('/admin/part-inquiries', $url);
        $this->assertStringContainsString('new', $url);
    }

    #[Test]
    public function parts_inquiry_widget_week_drilldown_links_to_index(): void
    {
        $url = PartInquiryResource::getUrl('index');

        $this->assertStringContainsString('/admin/part-inquiries', $url);
    }

    // ── FailedSearchesWidget ───────────────────────────────────────────────

    #[Test]
    public function failed_searches_inquire_drilldown_links_to_create_with_search_data(): void
    {
        $url = PartInquiryResource::getUrl('create', ['data' => ['search' => '06L906036L']]);

        $this->assertStringContainsString('/admin/part-inquiries/create', $url);
        $this->assertStringContainsString('data', $url);
    }

    // ── NewsletterGrowthWidget ─────────────────────────────────────────────

    #[Test]
    public function newsletter_growth_drilldown_links_to_subscribers(): void
    {
        $url = NewsletterSubscriberResource::getUrl('index');

        $this->assertStringContainsString('/admin/newsletter-subscribers', $url);
    }

    // ── ManufacturingStatsWidget ───────────────────────────────────────────

    #[Test]
    public function manufacturing_stats_drilldown_links_to_manufacturers(): void
    {
        $url = ManufacturerResource::getUrl('index');

        $this->assertStringContainsString('/admin/manufacturers', $url);
    }

    // ── QuickActionsWidget ─────────────────────────────────────────────────

    #[Test]
    public function quick_actions_new_product_drilldown(): void
    {
        $url = ProductResource::getUrl('create');

        $this->assertStringContainsString('/admin/products/create', $url);
    }

    #[Test]
    public function quick_actions_new_order_drilldown(): void
    {
        $url = OrderResource::getUrl('create');

        $this->assertStringContainsString('/admin/orders/create', $url);
    }

    #[Test]
    public function quick_actions_view_reports_drilldown(): void
    {
        $url = Reports::getUrl();

        $this->assertStringContainsString('/admin/reports', $url);
    }

    #[Test]
    public function quick_actions_system_settings_drilldown(): void
    {
        $url = Settings::getUrl();

        $this->assertStringContainsString('/admin/settings', $url);
    }

    #[Test]
    public function quick_actions_failed_search_logs_drilldown(): void
    {
        $url = FailedSearchLogResource::getUrl('index');

        $this->assertStringContainsString('/admin/failed-search-logs', $url);
    }

    // ── AdminUi::drilldown() Helper ────────────────────────────────────────

    #[Test]
    public function admin_ui_drilldown_returns_url_and_filters_array(): void
    {
        $result = AdminUi::drilldown('/admin/products', ['status' => 'active']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertEquals('/admin/products', $result['url']);
        $this->assertEquals(['status' => 'active'], $result['filters']);
    }

    #[Test]
    public function admin_ui_drilldown_defaults_to_empty_filters(): void
    {
        $result = AdminUi::drilldown('/admin/orders');

        $this->assertEquals([], $result['filters']);
    }
}
