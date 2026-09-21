<?php

namespace Tests\Feature;

use App\Filament\Widgets\AbandonedCartWidget;
use App\Filament\Widgets\AwaitingConfirmationList;
use App\Filament\Widgets\CacheStatusWidget;
use App\Filament\Widgets\Concerns\HasDashboardPeriod;
use App\Filament\Widgets\Concerns\HasPeriodFilterPills;
use App\Filament\Widgets\CustomerGrowthChart;
use App\Filament\Widgets\DashboardHeader;
use App\Filament\Widgets\DiskSpaceWidget;
use App\Filament\Widgets\FailedQueueJobsMonitor;
use App\Filament\Widgets\HealthStrip;
use App\Filament\Widgets\NewMessagesInbox;
use App\Filament\Widgets\OrderStatsOverview;
use App\Filament\Widgets\OrderStatusDistributionWidget;
use App\Filament\Widgets\OrderVolumeChart;
use App\Filament\Widgets\PartsInquiryWidget;
use App\Filament\Widgets\RecentActivityLog;
use App\Filament\Widgets\RefundsPendingList;
use App\Filament\Widgets\RequestMetricsWidget;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StockAlertWidget;
use App\Models\Admin;
use App\Services\WidgetPreferenceService;
use Carbon\CarbonInterface;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private WidgetPreferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingsSeeder::class,
            RolesSeeder::class,
        ]);

        $this->admin = Admin::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');

        $this->service = app(WidgetPreferenceService::class);
    }

    #[Test]
    public function period_defaults_to_30_when_no_preference_is_saved(): void
    {
        $this->assertSame('30', $this->service->getPeriod());
    }

    #[Test]
    public function save_period_persists_to_meta_and_is_rehydrated(): void
    {
        $this->service->savePeriod('7');

        // Verify raw column
        $prefs = $this->admin->fresh()->dashboard_preferences ?? [];
        $meta = $prefs[WidgetPreferenceService::META_KEY] ?? [];
        $this->assertSame('7', (string) ($meta['period'] ?? null));

        // Verify getPeriod() reads it back
        $this->assertSame('7', $this->service->getPeriod());
    }

    #[Test]
    public function all_valid_period_values_are_accepted(): void
    {
        foreach (['1', '7', '30', '90', '365'] as $period) {
            $this->service->savePeriod($period);
            $this->assertSame($period, $this->service->getPeriod(), "Period '{$period}' should be accepted");
        }
    }

    #[Test]
    public function invalid_period_is_rejected_and_previous_value_preserved(): void
    {
        $this->service->savePeriod('7');
        $this->service->savePeriod('99');

        $this->assertSame('7', $this->service->getPeriod());
    }

    #[Test]
    public function meta_period_survives_admin_model_save(): void
    {
        $this->service->savePeriod('90');

        // Saving the admin model must not clobber _meta.period
        $this->admin->touch();
        $this->admin->save();

        $this->assertSame('90', $this->service->getPeriod());
    }

    #[Test]
    public function period_start_for_today_is_start_of_day_not_24h_back(): void
    {
        // Cannot redeclare the trait property — set after construction instead.
        $widget = new class
        {
            use HasDashboardPeriod;

            public function getStart(): CarbonInterface
            {
                return $this->periodStart();
            }
        };
        $widget->period = '1';

        $start = $widget->getStart();

        $this->assertTrue(
            today()->equalTo($start),
            "period='1' must resolve to today() (midnight), not 24h back",
        );

        // Must NOT be yesterday
        $this->assertFalse(now()->subDay()->startOfDay()->equalTo($start));
    }

    #[Test]
    public function period_start_for_seven_days_is_one_week_back(): void
    {
        $widget = new class
        {
            use HasDashboardPeriod;

            public function getStart(): CarbonInterface
            {
                return $this->periodStart();
            }
        };
        $widget->period = '7';

        $start = $widget->getStart();
        $expected = now()->subDays(7);

        $this->assertLessThan(5, $start->diffInSeconds($expected), "period='7' must resolve to ~7 days ago");
    }

    #[Test]
    public function exempt_widgets_do_not_use_has_dashboard_period(): void
    {
        $exemptClasses = [
            DashboardHeader::class,
            HealthStrip::class,
            StockAlertWidget::class,
            RecentActivityLog::class,
            DiskSpaceWidget::class,
            RequestMetricsWidget::class,
            AbandonedCartWidget::class,
            PartsInquiryWidget::class,
            AwaitingConfirmationList::class,
            RefundsPendingList::class,
            NewMessagesInbox::class,
            FailedQueueJobsMonitor::class,
            CacheStatusWidget::class,
        ];

        foreach ($exemptClasses as $class) {
            $this->assertArrayNotHasKey(
                HasDashboardPeriod::class,
                class_uses_recursive($class),
                class_basename($class).' must NOT use HasDashboardPeriod (registry period=false)',
            );
        }
    }

    #[Test]
    public function all_period_capable_widgets_use_has_dashboard_period(): void
    {
        $capable = array_filter(WidgetPreferenceService::WIDGETS, fn ($c) => $c['period'] === true);

        foreach ($capable as $id => $config) {
            $this->assertArrayHasKey(
                HasDashboardPeriod::class,
                class_uses_recursive($config['class']),
                "Widget [{$id}] has period=true in registry but does not use HasDashboardPeriod",
            );
        }
    }

    #[Test]
    public function period_capable_and_exempt_registry_flags_are_consistent(): void
    {
        foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
            $usesTrait = isset(class_uses_recursive($config['class'])[HasDashboardPeriod::class]);
            $registryFlag = $config['period'];

            $this->assertSame(
                $registryFlag,
                $usesTrait,
                "Widget [{$id}] registry period={$this->boolStr($registryFlag)} but trait usage says {$this->boolStr($usesTrait)}",
            );
        }
    }

    private function boolStr(bool $v): string
    {
        return $v ? 'true' : 'false';
    }

    // ── The global period control (chart pill strips) ────────────────────

    #[Test]
    public function clicking_a_chart_pill_persists_the_period_and_broadcasts_it(): void
    {
        Livewire::test(RevenueChart::class)
            ->set('filter', '7')
            ->assertSet('period', '7')
            ->assertDispatched('period-changed', period: '7');

        $this->assertSame('7', $this->service->getPeriod(), 'pill choice must persist per admin');
    }

    #[Test]
    public function stat_widgets_follow_the_broadcast_period(): void
    {
        Livewire::test(OrderStatsOverview::class)
            ->dispatch('period-changed', period: '90')
            ->assertSet('period', '90');
    }

    #[Test]
    public function other_charts_sync_their_pill_highlight_to_the_broadcast(): void
    {
        Livewire::test(OrderVolumeChart::class)
            ->dispatch('period-changed', period: '365')
            ->assertSet('period', '365')
            ->assertSet('filter', '365');
    }

    #[Test]
    public function charts_hydrate_pills_from_the_persisted_period_on_mount(): void
    {
        $this->service->savePeriod('90');

        Livewire::test(CustomerGrowthChart::class)
            ->assertSet('filter', '90')
            ->assertSet('period', '90');
    }

    #[Test]
    public function every_chart_with_pills_also_participates_in_the_global_period(): void
    {
        foreach ([
            RevenueChart::class,
            OrderVolumeChart::class,
            OrderStatusDistributionWidget::class,
            CustomerGrowthChart::class,
        ] as $class) {
            $uses = class_uses_recursive($class);
            $this->assertArrayHasKey(HasPeriodFilterPills::class, $uses, class_basename($class));
            $this->assertArrayHasKey(HasDashboardPeriod::class, $uses, class_basename($class));
        }
    }
}
