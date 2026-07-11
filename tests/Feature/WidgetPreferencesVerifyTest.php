<?php

namespace Tests\Feature;

use App\Filament\Pages\WidgetPreferences;
use App\Models\Admin;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WidgetPreferencesVerifyTest extends TestCase
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
        $this->admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function preferences_page_loads_http(): void
    {
        $this->get('/admin/preferences/widgets')->assertStatus(200);
    }

    #[Test]
    public function preferences_page_mounts_with_grouped_widgets(): void
    {
        $page = Livewire::test(WidgetPreferences::class);
        $grouped = $page->get('groupedWidgets');
        // super_admin sees all 5 groups
        $this->assertEqualsCanonicalizing(
            array_keys(WidgetPreferenceService::GROUP_SLUGS),
            array_keys($grouped),
        );
        // ALWAYS_ON widgets must not appear
        $vis = $page->get('visibility');
        $this->assertArrayNotHasKey('dashboard_header', $vis);
        $this->assertArrayNotHasKey('health_strip', $vis);
    }

    #[Test]
    public function toggle_then_reset_round_trip(): void
    {
        $service = app(WidgetPreferenceService::class);
        $this->assertTrue($service->getVisibility('revenue_chart'));

        Livewire::test(WidgetPreferences::class)
            ->dispatch('toggle-widget', widgetId: 'revenue_chart');

        $this->admin->refresh();
        $this->assertFalse(app(WidgetPreferenceService::class)->getVisibility('revenue_chart'));

        Livewire::test(WidgetPreferences::class)
            ->call('resetToDefaults');

        $this->admin->refresh();
        $this->assertTrue(app(WidgetPreferenceService::class)->getVisibility('revenue_chart'));
    }

    #[Test]
    public function toggle_rejects_unknown_always_on_and_forbidden_widget_ids(): void
    {
        // §5q polish: toggleWidget used to persist ANY string into
        // dashboard_preferences and let a role toggle widgets it cannot see.
        Livewire::test(WidgetPreferences::class)
            ->call('toggleWidget', 'not_a_widget')
            ->call('toggleWidget', 'dashboard_header');

        $saved = ($this->admin->fresh()->dashboard_preferences ?? [])['widget_visibility'] ?? [];
        $this->assertArrayNotHasKey('not_a_widget', $saved);
        $this->assertArrayNotHasKey('dashboard_header', $saved);

        // A support admin cannot toggle a management-only widget.
        $support = Admin::create([
            'name' => 'Support Toggle Test',
            'email' => 'support-toggle@oeparts.test',
            'password' => bcrypt('password'),
        ]);
        $support->assignRole('support');
        $this->actingAs($support, 'admin');

        Livewire::test(WidgetPreferences::class)->call('toggleWidget', 'revenue_chart');

        $saved = ($support->fresh()->dashboard_preferences ?? [])['widget_visibility'] ?? [];
        $this->assertArrayNotHasKey('revenue_chart', $saved);
    }

    #[Test]
    public function dashboard_hides_toggled_off_widget(): void
    {
        $service = app(WidgetPreferenceService::class);
        $service->saveVisibility('revenue_chart', false);
        $this->admin->refresh();

        $widgets = app(\App\Filament\Pages\Dashboard::class)->getWidgets();
        $classes = array_map(
            fn ($w) => $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w,
            $widgets,
        );
        $this->assertNotContains(\App\Filament\Widgets\RevenueChart::class, $classes);
        // always-on still present
        $this->assertContains(\App\Filament\Widgets\DashboardHeader::class, $classes);
    }

    #[Test]
    public function catalog_admin_sees_only_role_groups_no_system_health(): void
    {
        $catalog = Admin::create([
            'name' => 'Catalog Test',
            'email' => 'catalog-verify@oeparts.test',
            'password' => bcrypt('password'),
        ]);
        $catalog->assignRole('catalog_admin');
        $this->actingAs($catalog, 'admin');

        $grouped = Livewire::test(WidgetPreferences::class)->get('groupedWidgets');
        $this->assertArrayNotHasKey('system-health', $grouped);
    }

    #[Test]
    public function dashboard_widgets_render_in_registry_sort_order(): void
    {
        // Layout order is driven by the registry's default_sort (groups in
        // sequence, widgets ordered within each group) — NOT each widget
        // class's legacy $sort. Regression guard: Filament preserves
        // getWidgets() array order, so getWidgets() must return widgets sorted
        // by sortFor(). (Bug: health_strip rendered at the bottom and groups
        // were interleaved because the legacy $sort values were arbitrary.)
        $classes = array_map(
            fn ($w) => $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w,
            app(\App\Filament\Pages\Dashboard::class)->getWidgets(),
        );

        // Drop the injected structural group-header widgets — only content
        // widgets carry a registry default_sort.
        $classes = array_values(array_filter(
            $classes,
            fn ($c) => $c !== \App\Filament\Widgets\GroupHeaderWidget::class,
        ));

        $sorts = array_map(
            fn ($c) => WidgetPreferenceService::sortFor($c),
            $classes,
        );

        $sortedAscending = $sorts;
        sort($sortedAscending);

        $this->assertSame(
            $sortedAscending,
            $sorts,
            'Dashboard widgets must render in registry default_sort order. Got: '
                . implode(',', array_map(fn ($c) => class_basename($c), $classes)),
        );

        // health_strip must come before every business/needs-attention widget.
        $healthIdx = array_search(\App\Filament\Widgets\HealthStrip::class, $classes, true);
        $ordersIdx = array_search(\App\Filament\Widgets\RecentOrdersList::class, $classes, true);
        if ($healthIdx !== false && $ordersIdx !== false) {
            $this->assertLessThan($ordersIdx, $healthIdx, 'health_strip must render near the top, before content widgets.');
        }
    }

    #[Test]
    public function group_headers_are_injected_per_visible_group_in_order(): void
    {
        $widgets = app(\App\Filament\Pages\Dashboard::class)->getWidgets();

        // Walk the list; collect (header-label | content-id) sequence.
        $seq = [];
        $service = app(WidgetPreferenceService::class);
        foreach ($widgets as $w) {
            if ($w instanceof \Filament\Widgets\WidgetConfiguration && $w->widget === \App\Filament\Widgets\GroupHeaderWidget::class) {
                $seq[] = 'HEADER:' . $w->getProperties()['label'];
            } else {
                $class = $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w;
                $seq[] = $service->getWidgetId($class) ?? class_basename($class);
            }
        }

        // Always-on widgets come first and have NO header before them.
        $this->assertSame('dashboard_header', $seq[0], 'dashboard_header must be first with no header above it.');

        // The first header must be Business Overview, appearing right before order_stats_overview.
        $boHeaderIdx = array_search('HEADER:Business Overview', $seq, true);
        $statsIdx = array_search('order_stats_overview', $seq, true);
        $this->assertNotFalse($boHeaderIdx, 'Business Overview header must be present.');
        $this->assertSame($statsIdx - 1, $boHeaderIdx, 'Business Overview header must immediately precede order_stats_overview.');

        // For super_admin defaults, exactly these groups have visible widgets.
        $headers = array_values(array_filter($seq, fn ($s) => str_starts_with($s, 'HEADER:')));
        $this->assertSame(
            ['HEADER:Business Overview', 'HEADER:Needs Attention', 'HEADER:Live Activity'],
            $headers,
            'Headers must appear once per non-empty group, in registry group order.',
        );
    }
}
