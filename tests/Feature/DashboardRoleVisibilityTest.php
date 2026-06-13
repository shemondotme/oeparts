<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\WidgetPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardRoleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const FINANCIAL_WIDGETS = [
        \App\Filament\Widgets\RevenueChart::class,
        \App\Filament\Widgets\DashboardKpiStats::class,
        \App\Filament\Widgets\TopManufacturersRevenue::class,
        \App\Filament\Widgets\PaymentMethodSplit::class,
        \App\Filament\Widgets\CouponUsageWidget::class,
        \App\Filament\Widgets\ActivityOverviewWidget::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function can_view_matrix_matches_the_registry_for_every_role(): void
    {
        foreach (['super_admin', 'admin', 'manager', 'catalog_admin', 'support'] as $role) {
            $admin = $this->adminWithRole($role);
            $this->actingAs($admin, 'admin');

            foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
                $expected = $role === 'super_admin' || in_array($role, $config['roles'], true);

                $this->assertSame(
                    // super_admin is in every registry roles list by design;
                    // assert directly against the registry.
                    in_array($role, $config['roles'], true),
                    $config['class']::canView(),
                    "Widget [{$id}] canView mismatch for role [{$role}]",
                );
            }

            auth('admin')->logout();
        }
    }

    #[Test]
    public function financial_widgets_are_hidden_from_catalog_admin_and_support(): void
    {
        foreach (['catalog_admin', 'support'] as $role) {
            $admin = $this->adminWithRole($role);
            $this->actingAs($admin, 'admin');

            foreach (self::FINANCIAL_WIDGETS as $class) {
                $this->assertFalse(
                    $class::canView(),
                    class_basename($class) . " must be hidden from {$role}",
                );
            }

            auth('admin')->logout();
        }
    }

    #[Test]
    public function catalog_admin_and_support_get_a_populated_dashboard(): void
    {
        foreach (['catalog_admin', 'support'] as $role) {
            $admin = $this->adminWithRole($role);
            $this->actingAs($admin, 'admin');

            $service = app(\App\Services\DashboardLayoutService::class);
            $dashboard = $service->ensureDefaultDashboard($admin);

            // Layout must contain the role's curated widget ids
            $this->assertSame(
                WidgetPreferenceService::ROLE_DEFAULT_DASHBOARDS[$role],
                array_column($dashboard->layout, 'id'),
                "{$role} layout must match ROLE_DEFAULT_DASHBOARDS",
            );

            // At least some widgets must pass canView() for this role.
            // Note: canView() reads auth('admin')->user() from the current
            // request; re-establish actingAs after any HTTP request calls.
            $items = $service->canvasItems($admin, $dashboard);
            $this->assertNotEmpty($items, "{$role} dashboard canvas must not be empty");

            auth('admin')->logout();
        }
    }

    #[Test]
    public function dashboard_loads_for_every_role(): void
    {
        foreach (['super_admin', 'admin', 'manager', 'catalog_admin', 'support'] as $role) {
            $admin = $this->adminWithRole($role);
            $this->actingAs($admin, 'admin');

            // Filament may redirect restricted roles (catalog_admin, support)
            // to their first accessible nav item. Follow the chain and assert
            // that the panel ultimately renders without error.
            $this->followingRedirects()->get('/admin')->assertSuccessful();

            auth('admin')->logout();
        }
    }

    #[Test]
    public function every_registry_entry_lists_super_admin(): void
    {
        foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
            $this->assertContains('super_admin', $config['roles'], "Widget [{$id}] must allow super_admin");
        }
    }
}
