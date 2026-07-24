<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\CheckoutDropoffReport;
use App\Filament\Pages\Reports\CustomersReport;
use App\Filament\Pages\Reports\SalesReport;
use App\Filament\Pages\Reports\SearchIntelligenceReport;
use App\Filament\Widgets\Reports\SalesStats;
use App\Models\Admin;
use App\Models\Order;
use App\Services\AdminWidgetCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Performance sweep Stage D: Reports-page widgets (Sales/Customers/Checkout/
 * Search) previously re-ran raw JOIN/GROUP BY aggregates on every load — now
 * wrapped in InteractsWithDashboardCache like the main-dashboard widgets.
 */
class ReportsWidgetCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\SettingsSeeder::class, \Database\Seeders\RolesSeeder::class]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function all_four_report_pages_still_render_after_caching_the_widgets(): void
    {
        foreach ([SalesReport::class, CustomersReport::class, CheckoutDropoffReport::class, SearchIntelligenceReport::class] as $page) {
            Livewire::test($page)->assertOk();
        }
    }

    #[Test]
    public function sales_stats_widget_hits_cache_on_second_render_within_the_same_period(): void
    {
        AdminWidgetCacheService::$bypassArrayDriverCheck = true;

        Order::factory()->count(2)->create(['created_at' => now()]);

        // First mount populates the cache.
        Livewire::test(SalesStats::class, ['period' => '30']);

        DB::enableQueryLog();
        Livewire::test(SalesStats::class, ['period' => '30']);
        $queryCount = count(array_filter(
            DB::getQueryLog(),
            fn (array $q) => str_contains($q['query'], 'orders'),
        ));
        DB::disableQueryLog();

        AdminWidgetCacheService::$bypassArrayDriverCheck = false;

        $this->assertSame(0, $queryCount, 'Second call within the same period must be served entirely from cache');
    }
}
