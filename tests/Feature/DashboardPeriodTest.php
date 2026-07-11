<?php

namespace Tests\Feature;

use App\Filament\Widgets\Concerns\HasDashboardPeriod;
use App\Models\Admin;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
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
        $widget = new class {
            use HasDashboardPeriod;

            public function getStart(): \Carbon\CarbonInterface
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
        $widget = new class {
            use HasDashboardPeriod;

            public function getStart(): \Carbon\CarbonInterface
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
            \App\Filament\Widgets\DashboardHeader::class,
            \App\Filament\Widgets\HealthStrip::class,
            \App\Filament\Widgets\StockAlertWidget::class,
            \App\Filament\Widgets\RecentActivityLog::class,
            \App\Filament\Widgets\DiskSpaceWidget::class,
            \App\Filament\Widgets\RequestMetricsWidget::class,
            \App\Filament\Widgets\AbandonedCartWidget::class,
            \App\Filament\Widgets\PartsInquiryWidget::class,
            \App\Filament\Widgets\AwaitingConfirmationList::class,
            \App\Filament\Widgets\RefundsPendingList::class,
            \App\Filament\Widgets\NewMessagesInbox::class,
            \App\Filament\Widgets\FailedQueueJobsMonitor::class,
            \App\Filament\Widgets\CacheStatusWidget::class,
        ];

        foreach ($exemptClasses as $class) {
            $this->assertArrayNotHasKey(
                HasDashboardPeriod::class,
                class_uses_recursive($class),
                class_basename($class) . ' must NOT use HasDashboardPeriod (registry period=false)',
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
}
