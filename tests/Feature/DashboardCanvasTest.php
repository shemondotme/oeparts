<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminDashboard;
use App\Services\DashboardLayoutService;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardCanvasTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private DashboardLayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->admin = Admin::where('email', 'admin@oeparts.test')->firstOrFail();
        $this->actingAs($this->admin, 'admin');

        $this->service = app(DashboardLayoutService::class);
    }

    #[Test]
    public function default_dashboard_is_seeded_from_role_defaults(): void
    {
        $dashboard = $this->service->ensureDefaultDashboard($this->admin);

        $this->assertTrue($dashboard->is_default);
        $this->assertSame('Command Center', $dashboard->name);

        $ids = array_column($dashboard->layout, 'id');
        $expectedIds = array_values(array_filter(
            WidgetPreferenceService::WIDGET_TABS['Command Center'],
            fn (string $id): bool => $this->admin->hasAnyRole(WidgetPreferenceService::WIDGETS[$id]['roles'] ?? [])
        ));
        $this->assertSame(
            $expectedIds,
            $ids,
        );
    }

    #[Test]
    public function ensure_default_dashboard_is_idempotent(): void
    {
        $first = $this->service->ensureDefaultDashboard($this->admin);
        $second = $this->service->ensureDefaultDashboard($this->admin);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(4, AdminDashboard::where('admin_id', $this->admin->id)->count());
    }

    #[Test]
    public function legacy_preferences_seed_the_first_dashboard(): void
    {
        // Hide a default-visible widget the legacy way before first canvas load.
        app(WidgetPreferenceService::class)->toggle('revenue_chart', false);

        $dashboard = $this->service->ensureDefaultDashboard($this->admin);

        $ids = array_column($dashboard->layout, 'id');
        $this->assertNotContains('revenue_chart', $ids);
        $this->assertContains('recent_orders', $ids);
    }

    #[Test]
    public function named_dashboards_can_be_created_renamed_and_deleted(): void
    {
        $this->service->ensureDefaultDashboard($this->admin);

        $finance = $this->service->create($this->admin, 'Finance');
        $this->assertSame('finance', $finance->slug);
        $this->assertSame($finance->id, app(WidgetPreferenceService::class)->getActiveDashboardId());

        // Duplicate name gets a unique slug.
        $finance2 = $this->service->create($this->admin, 'Finance');
        $this->assertSame('finance-2', $finance2->slug);

        $this->service->rename($this->admin, $finance->id, 'Money Ops');
        $this->assertSame('Money Ops', $finance->fresh()->name);

        $this->assertTrue($this->service->delete($this->admin, $finance->id));
        $this->assertTrue($this->service->delete($this->admin, $finance2->id));
    }

    #[Test]
    public function the_last_dashboard_cannot_be_deleted(): void
    {
        $this->service->ensureDefaultDashboard($this->admin);

        // Delete all except one
        $dashboards = AdminDashboard::where('admin_id', $this->admin->id)->get();
        foreach ($dashboards->skip(1) as $d) {
            $this->assertTrue($this->service->delete($this->admin, $d->id));
        }

        $last = $dashboards->first();
        $this->assertFalse($this->service->delete($this->admin, $last->id));
        $this->assertSame(1, AdminDashboard::where('admin_id', $this->admin->id)->count());
    }

    #[Test]
    public function save_layout_persists_valid_items_and_clamps_bounds(): void
    {
        $dashboard = $this->service->ensureDefaultDashboard($this->admin);

        $saved = $this->service->saveLayout($this->admin, $dashboard->id, [
            ['id' => 'dashboard_header', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
            // Out of bounds: x+w > 12 → x is pulled back.
            ['id' => 'revenue_chart', 'x' => 10, 'y' => 2, 'w' => 8, 'h' => 4],
            // Oversized h is clamped to 12.
            ['id' => 'recent_orders', 'x' => 0, 'y' => 6, 'w' => 6, 'h' => 99],
        ]);

        $byId = collect($saved)->keyBy('id');

        $this->assertSame(4, $byId['revenue_chart']['x']);
        $this->assertSame(12, $byId['recent_orders']['h']);
        $this->assertCount(3, $dashboard->fresh()->layout);
    }

    #[Test]
    public function save_layout_rejects_unknown_and_forbidden_widget_ids(): void
    {
        $dashboard = $this->service->ensureDefaultDashboard($this->admin);

        $support = Admin::factory()->create();
        $support->assignRole('support');

        $saved = $this->service->saveLayout($support, $this->service->ensureDefaultDashboard($support)->id, [
            ['id' => 'totally_fake_widget', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2],
            // Financial widget — support role may not place it.
            ['id' => 'revenue_chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
            ['id' => 'recent_orders', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        ]);

        $this->assertSame(['recent_orders'], array_column($saved, 'id'));
    }

    #[Test]
    public function switching_dashboards_changes_the_active_canvas(): void
    {
        $default = $this->service->ensureDefaultDashboard($this->admin);
        $second = $this->service->create($this->admin, 'Second');

        $this->service->switchTo($this->admin, $default->id);
        $this->assertSame($default->id, $this->service->activeDashboard($this->admin)->id);

        $this->service->switchTo($this->admin, $second->id);
        $this->assertSame($second->id, $this->service->activeDashboard($this->admin)->id);
    }

    #[Test]
    public function set_widgets_keeps_coordinates_of_retained_widgets(): void
    {
        $dashboard = $this->service->ensureDefaultDashboard($this->admin);

        $this->service->saveLayout($this->admin, $dashboard->id, [
            ['id' => 'revenue_chart', 'x' => 3, 'y' => 5, 'w' => 9, 'h' => 4],
        ]);

        $this->service->setWidgets($this->admin, $dashboard->id, ['revenue_chart', 'recent_orders']);

        $layout = collect($dashboard->fresh()->layout)->keyBy('id');

        $this->assertSame(3, $layout['revenue_chart']['x']);
        $this->assertSame(5, $layout['revenue_chart']['y']);
        $this->assertTrue($layout->has('recent_orders'));
        // Appended widget lands below the retained block.
        $this->assertGreaterThanOrEqual(9, $layout['recent_orders']['y']);
    }
}
