<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\AdminNavService;
use App\Services\DashboardLayoutService;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardLayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private WidgetPreferenceService $preferences;

    private DashboardLayoutService $layout;

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

        $this->preferences = app(WidgetPreferenceService::class);
        $this->layout = app(DashboardLayoutService::class);
    }

    #[Test]
    public function sidebar_nav_housekeeping_is_excluded_from_widget_preferences(): void
    {
        // Simulate an admin who has merely clicked around the sidebar —
        // AdminNavService writes pinned_nav/recent_nav onto the SAME
        // dashboard_preferences column WidgetPreferenceService owns.
        AdminNavService::recordVisit($this->admin, '/admin/orders', 'Orders', '/admin/orders', 'Commerce');

        $rawColumn = $this->admin->fresh()->dashboard_preferences ?? [];
        $this->assertArrayHasKey('recent_nav', $rawColumn, 'precondition: nav housekeeping must actually be present on the column');

        $this->assertSame(
            [],
            $this->preferences->getPreferences(),
            'recent_nav/pinned_nav must never be mistaken for real widget preferences',
        );
    }

    #[Test]
    public function admin_with_only_nav_history_still_seeds_from_the_command_center_blueprint(): void
    {
        // Same precondition as above, but exercised through the real seeding
        // path this bug actually broke: ensureDefaultDashboard() must still
        // pick the curated TAB_BLUEPRINT_LAYOUTS branch, not the naive
        // auto-pack ('my-dashboard') branch, for an admin who has nav
        // history but no real widget preferences.
        AdminNavService::recordVisit($this->admin, '/admin/orders', 'Orders', '/admin/orders', 'Commerce');

        $dashboard = $this->layout->ensureDefaultDashboard($this->admin);

        $this->assertSame('command-center', $dashboard->slug);

        $byId = collect($dashboard->layout)->keyBy('id');

        foreach (DashboardLayoutService::TAB_BLUEPRINT_LAYOUTS['command-center'] as $expected) {
            $actual = $byId->get($expected['id']);

            $this->assertNotNull($actual, "widget [{$expected['id']}] missing from seeded layout");
            $this->assertSame($expected['x'], $actual['x'], "widget [{$expected['id']}] x mismatch");
            $this->assertSame($expected['y'], $actual['y'], "widget [{$expected['id']}] y mismatch");
            $this->assertSame($expected['w'], $actual['w'], "widget [{$expected['id']}] w mismatch");
            $this->assertSame($expected['h'], $actual['h'], "widget [{$expected['id']}] h mismatch");
        }
    }

    #[Test]
    public function admin_with_real_legacy_widget_preferences_still_uses_the_legacy_pack_path(): void
    {
        // Guard against over-correcting: a genuinely customized admin
        // (real per-widget hidden/sort prefs) must still take the legacy
        // 'my-dashboard' auto-pack branch — this is existing, intended
        // behavior and must not regress.
        $this->preferences->toggle('top_searches', false);

        $dashboard = $this->layout->ensureDefaultDashboard($this->admin);

        $this->assertSame('my-dashboard', $dashboard->slug);
    }

    #[Test]
    public function admin_with_no_preferences_at_all_still_seeds_from_blueprint(): void
    {
        // Brand-new admin, never visited any page — the already-correct
        // first-time-seed path must be unchanged by this fix.
        $dashboard = $this->layout->ensureDefaultDashboard($this->admin);

        $this->assertSame('command-center', $dashboard->slug);
    }

    #[Test]
    public function canvas_items_respects_a_blueprints_intentionally_smaller_height(): void
    {
        // parts_inquiry's bare registry default_layout is h:3, but the
        // command-center blueprint deliberately sets it to h:2 to sit
        // compactly beside order_stats_overview. canvasItems() must not
        // clamp it back up to the bare default — that pushed every widget
        // below it down by a full row.
        $dashboard = $this->layout->ensureDefaultDashboard($this->admin);

        $items = collect($this->layout->canvasItems($this->admin, $dashboard))->keyBy('id');

        $this->assertSame(2, $items['parts_inquiry']['h']);
        $this->assertSame(2, $items['parts_inquiry']['minH']);

        // And the rows below must not have been pushed down as a result.
        $this->assertSame(5, $items['revenue_chart']['y']);
        $this->assertSame(5, $items['order_volume_chart']['y']);
        $this->assertSame(10, $items['order_status_distribution']['y']);
        $this->assertSame(10, $items['latest_customers']['y']);
    }
}
