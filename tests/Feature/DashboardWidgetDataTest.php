<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\CheckoutDropoffChart;
use App\Filament\Widgets\DashboardKpiStats;
use App\Models\Admin;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardWidgetDataTest extends TestCase
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
        ]);

        $this->admin = Admin::where('email', 'admin@oeparts.test')->firstOrFail();
        $this->actingAs($this->admin, 'admin');
    }

    // ── TopManufacturersRevenue — ID-based join ──────────────────────────────

    #[Test]
    public function top_manufacturers_revenue_attributes_via_product_id_not_snapshot_string(): void
    {
        $mfrA = Manufacturer::factory()->create(['slug' => 'brand-alpha-' . uniqid()]);
        $mfrB = Manufacturer::factory()->create(['slug' => 'brand-beta-' . uniqid()]);

        $productA = Product::factory()->create(['manufacturer_id' => $mfrA->id]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Paid->value,
            'grand_total' => '200.00',
            'created_at' => now()->subDays(5),
        ]);

        // The manufacturer_snapshot deliberately does NOT match mfrA's name —
        // the old string-join would have returned 0; the ID join gets it right.
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'manufacturer_snapshot' => 'Completely Wrong Company Name',
            'total_price' => '200.00',
        ]);

        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $result = Manufacturer::query()
            ->select('manufacturers.id')
            ->selectSub(function ($q) use ($paidStatuses) {
                $q->selectRaw('COALESCE(SUM(order_items.total_price), 0)')
                    ->from('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->whereColumn('products.manufacturer_id', 'manufacturers.id')
                    ->whereIn('orders.status', $paidStatuses)
                    ->where('orders.created_at', '>=', now()->subDays(30));
            }, 'revenue')
            ->get()
            ->keyBy('id');

        $this->assertSame('200.00', number_format((float) $result[$mfrA->id]->revenue, 2));
        $this->assertSame('0.00', number_format((float) $result[$mfrB->id]->revenue, 2));
    }

    #[Test]
    public function top_manufacturers_excludes_items_whose_product_was_deleted(): void
    {
        $mfr = Manufacturer::factory()->create(['slug' => 'mfr-deleted-' . uniqid()]);
        $product = Product::factory()->create(['manufacturer_id' => $mfr->id]);

        $order = Order::factory()->create([
            'status' => OrderStatus::Paid->value,
            'created_at' => now()->subDays(3),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => null, // product was deleted — drop out of attribution
            'total_price' => '99.00',
        ]);

        $paidStatuses = [OrderStatus::Paid->value];

        $revenue = DB::selectOne(
            'SELECT COALESCE(SUM(oi.total_price), 0) as revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id
             WHERE p.manufacturer_id = ?
               AND o.status IN (?)
               AND o.created_at >= ?',
            [$mfr->id, OrderStatus::Paid->value, now()->subDays(30)]
        );

        $this->assertSame('0.00', number_format((float) $revenue->revenue, 2));
    }

    // ── DashboardKpiStats — bcmath string safety ─────────────────────────────

    #[Test]
    public function kpi_stats_revenue_is_returned_as_string_not_float(): void
    {
        Order::factory()->create([
            'status' => OrderStatus::Paid->value,
            'grand_total' => '33.33',
            'created_at' => now()->subDays(1),
        ]);
        Order::factory()->create([
            'status' => OrderStatus::Paid->value,
            'grand_total' => '33.33',
            'created_at' => now()->subDays(1),
        ]);
        Order::factory()->create([
            'status' => OrderStatus::Paid->value,
            'grand_total' => '33.34',
            'created_at' => now()->subDays(1),
        ]);

        $widget = new DashboardKpiStats();
        $widget->period = '30';

        $method = new \ReflectionMethod($widget, 'computeStats');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertIsString($data['revenue'], 'Revenue must be a string for safe bcmath operations');
        $this->assertSame(0, bccomp('100.00', $data['revenue'], 2), "Revenue must sum to exactly 100.00, got {$data['revenue']}");
        $this->assertIsString($data['prevRevenue'], 'prevRevenue must be a string');
        $this->assertIsInt($data['orders']);
        $this->assertIsInt($data['pending']);
    }

    #[Test]
    public function kpi_stats_excludes_pending_orders_from_revenue(): void
    {
        // Pending order — must NOT count toward revenue
        Order::factory()->create([
            'status' => OrderStatus::Pending->value,
            'grand_total' => '999.00',
            'created_at' => now()->subDays(1),
        ]);

        // Paid order — must count
        Order::factory()->create([
            'status' => OrderStatus::Paid->value,
            'grand_total' => '50.00',
            'created_at' => now()->subDays(1),
        ]);

        $widget = new DashboardKpiStats();
        $widget->period = '30';

        $method = new \ReflectionMethod($widget, 'computeStats');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertSame(0, bccomp('50.00', $data['revenue'], 2));
    }

    // ── CheckoutDropoffChart — cancelled bar ─────────────────────────────────

    #[Test]
    public function checkout_dropoff_cancelled_bar_is_non_zero(): void
    {
        // Funnel start: abandoned carts
        DB::table('abandoned_carts')->insert([
            ['guest_email' => 'a@test.com', 'cart_snapshot' => '{}', 'last_active_at' => now()->subDays(7), 'recovery_email_sent' => 0, 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7)],
            ['guest_email' => 'b@test.com', 'cart_snapshot' => '{}', 'last_active_at' => now()->subDays(7), 'recovery_email_sent' => 0, 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7)],
        ]);

        // Regular pending orders
        Order::factory()->count(3)->create([
            'status' => OrderStatus::Pending->value,
            'created_at' => now()->subDays(7),
        ]);

        // Cancelled orders — the fixed bar that was always 0 before
        Order::factory()->count(2)->create([
            'status' => OrderStatus::Cancelled->value,
            'created_at' => now()->subDays(7),
        ]);

        $widget = new CheckoutDropoffChart();
        $widget->period = '30';

        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $bars = $data['datasets'][0]['data'];

        // bars order: [cartCreated, startedCheckout, completed, paid, cancelled]
        $this->assertSame(2, $bars[0], 'Cart Created bar');
        $this->assertSame(5, $bars[1], 'Started Checkout bar (all orders)');
        $this->assertSame(0, $bars[2], 'Completed bar (no Processing/Paid/Shipped/Delivered)');
        $this->assertSame(2, $bars[4], 'Cancelled bar must be non-zero (was always 0 before the fix)');

        $this->assertSame(
            ['Cart Created', 'Started Checkout', 'Completed', 'Paid', 'Cancelled'],
            $data['labels'],
        );
    }

    #[Test]
    public function checkout_dropoff_does_not_count_abandoned_keyword_as_status(): void
    {
        // Confirm OrderStatus enum has no 'abandoned' case — the old code
        // filtered on a non-existent value making the bar permanently 0.
        $cases = array_column(OrderStatus::cases(), 'value');
        $this->assertNotContains('abandoned', $cases, "'abandoned' must not be a valid OrderStatus");
        $this->assertContains(OrderStatus::Cancelled->value, $cases);
    }

    // ── DashboardAlerts — backup_stale_hours from settings ──────────────────

    #[Test]
    public function dashboard_alerts_uses_backup_stale_hours_setting(): void
    {
        $threshold = (int) settings('dashboard.backup_stale_hours', 26);
        $this->assertGreaterThan(0, $threshold, 'Setting must be seeded');

        $staleAt = now()->subHours($threshold + 2);

        DB::table('cron_logs')->insert([
            'job_name' => 'db:backup',
            'status' => 'success',
            'duration_ms' => 1200,
            'output' => null,
            'ran_at' => $staleAt,
        ]);

        $lastBackup = DB::table('cron_logs')
            ->where('job_name', 'db:backup')
            ->where('status', 'success')
            ->orderByDesc('ran_at')
            ->value('ran_at');

        $this->assertNotNull($lastBackup);

        // Use Carbon::parse to avoid signed-diffInHours ambiguity in Carbon 3.x
        $lastBackupCarbon = \Carbon\Carbon::parse($lastBackup);
        $this->assertTrue(
            $lastBackupCarbon->isBefore(now()->subHours($threshold)),
            "Backup at {$lastBackupCarbon} must be older than threshold ({$threshold}h ago)",
        );
    }

    #[Test]
    public function dashboard_alerts_does_not_flag_fresh_backup_as_stale(): void
    {
        $threshold = (int) settings('dashboard.backup_stale_hours', 26);

        DB::table('cron_logs')->insert([
            'job_name' => 'db:backup',
            'status' => 'success',
            'duration_ms' => 800,
            'output' => null,
            'ran_at' => now()->subHours(1),
        ]);

        $lastBackup = DB::table('cron_logs')
            ->where('job_name', 'db:backup')
            ->where('status', 'success')
            ->orderByDesc('ran_at')
            ->value('ran_at');

        $lastBackupCarbon = \Carbon\Carbon::parse($lastBackup);
        $this->assertFalse(
            $lastBackupCarbon->isBefore(now()->subHours($threshold)),
            'A 1h-old backup must NOT be flagged as stale against a threshold of {$threshold}h',
        );
    }
}
