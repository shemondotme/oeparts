<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use App\Models\Admin;
use App\Models\BulkUpdateLog;
use App\Models\Order;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRouteBlockersTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Route Admin',
            'email' => 'route-admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function cms_section_show_page_renders(): void
    {
        $section = Section::create([
            'type' => 'hero',
            'location' => SectionLocation::Homepage,
            'title' => ['en' => 'Homepage Hero'],
            'content' => ['en' => ['heading' => 'Find OEM parts']],
            'status' => SectionStatus::Published,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $section->saveVersion('created', $this->admin->id, 'Initial test version');

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.cms.sections.show', $section));

        $response->assertOk();
        $response->assertSee('Homepage Hero');
        $response->assertSee('Version History');
    }

    #[Test]
    public function bulk_update_log_detail_page_supports_generic_log_format(): void
    {
        $log = BulkUpdateLog::create([
            'admin_id' => $this->admin->id,
            'entity_type' => 'products',
            'filters' => ['is_active' => '1'],
            'updates' => ['is_in_stock' => true],
            'affected_rows_count' => 7,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.catalog.bulk-update.logs.show', $log));

        $response->assertOk();
        $response->assertSee('Bulk Update Log');
        $response->assertSee('products');
        $response->assertSee('7 records');
    }

    #[Test]
    public function reports_export_downloads_csv_instead_of_flash_only(): void
    {
        Order::create([
            'order_number' => 'ORD-EXPORT-001',
            'status' => OrderStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '10.00',
            'vat_amount' => '21.00',
            'grand_total' => '131.00',
            'shipping_name' => 'Export Buyer',
            'shipping_address_line1' => '1 Export Road',
            'shipping_city' => 'Paris',
            'shipping_postal_code' => '75001',
            'shipping_country_code' => 'FR',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('ORD-EXPORT-001', $csv);
        $this->assertStringContainsString('grand_total', $csv);
    }
}
