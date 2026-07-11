<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\GroupHeaderWidget;
use App\Models\Admin;
use App\Services\WidgetPreferenceService;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks the dashboard's composition to the WidgetPreferenceService registry.
 *
 * Regression for two audit findings (§5q #1/#2): panel-wide discovery also
 * picks up the Reports/System page widgets, which leaked onto the dashboard
 * because unregistered classes fell through as visible; and chart widgets
 * must render eagerly because async-alpine never initializes the `chart`
 * Alpine component on lazily-morphed HTML (blank canvas, zero errors).
 */
class DashboardCompositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    /** @return list<class-string> widget classes the dashboard resolved to render */
    private function dashboardWidgetClasses(): array
    {
        $widgets = Livewire::test(Dashboard::class)->instance()->getWidgets();

        return array_values(array_map(
            fn ($widget): string => $widget instanceof WidgetConfiguration ? $widget->widget : $widget,
            $widgets,
        ));
    }

    #[Test]
    public function dashboard_renders_only_registry_widgets(): void
    {
        $service = app(WidgetPreferenceService::class);

        foreach ($this->dashboardWidgetClasses() as $class) {
            if ($class === GroupHeaderWidget::class) {
                continue; // structural, injected by withGroupHeaders()
            }

            $this->assertNotNull(
                $service->getWidgetId($class),
                "Dashboard rendered [{$class}], which is not in the widget registry",
            );
        }
    }

    #[Test]
    public function reports_and_system_page_widgets_never_leak_onto_the_dashboard(): void
    {
        $classes = $this->dashboardWidgetClasses();

        foreach ($classes as $class) {
            $this->assertStringNotContainsString('Widgets\\Reports\\', $class);
            $this->assertStringNotContainsString('Widgets\\System\\', $class);
        }

        // The registry widgets themselves still render for super_admin.
        $this->assertContains(\App\Filament\Widgets\RevenueChart::class, $classes);
        $this->assertContains(\App\Filament\Widgets\HealthStrip::class, $classes);
    }

    #[Test]
    public function chart_widgets_render_eagerly(): void
    {
        foreach ([
            \App\Filament\Widgets\RevenueChart::class,
            \App\Filament\Widgets\OrderVolumeChart::class,
            \App\Filament\Widgets\OrderStatusDistributionWidget::class,
            \App\Filament\Widgets\CustomerGrowthChart::class,
        ] as $class) {
            $this->assertFalse(
                $class::isLazy(),
                class_basename($class) . ' must be eager — async-alpine never initializes charts on lazily-morphed HTML',
            );
        }
    }
}
