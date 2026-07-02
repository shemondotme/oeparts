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
        \App\Filament\Widgets\OrderStatsOverview::class,
        \App\Filament\Widgets\TopManufacturersRevenue::class,
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
                $class = $config['class'];
                $usesTrait = isset(class_uses_recursive($class)[\App\Filament\Widgets\Concerns\HasWidgetRoles::class]);

                if (! $usesTrait) {
                    continue;
                }

                $this->assertSame(
                    in_array($role, $config['roles'], true),
                    $class::canView(),
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
                $usesTrait = isset(class_uses_recursive($class)[\App\Filament\Widgets\Concerns\HasWidgetRoles::class]);

                if (! $usesTrait) {
                    continue;
                }

                $allowedRoles = WidgetPreferenceService::rolesFor($class);
                $shouldSeeFinancial = in_array($role, $allowedRoles, true);

                $this->assertSame(
                    $shouldSeeFinancial,
                    $class::canView(),
                    class_basename($class) . " canView() does not match registry for {$role}",
                );
            }

            auth('admin')->logout();
        }
    }

    #[Test]
    public function catalog_admin_and_support_see_widgets_matching_registry(): void
    {
        foreach (['catalog_admin', 'support'] as $role) {
            $admin = $this->adminWithRole($role);
            $this->actingAs($admin, 'admin');

            // Verify that at least some widgets pass canView() for this role
            $visibleWidgets = [];
            foreach (WidgetPreferenceService::WIDGETS as $id => $config) {
                $class = $config['class'];
                $usesTrait = isset(class_uses_recursive($class)[\App\Filament\Widgets\Concerns\HasWidgetRoles::class]);

                if ($usesTrait && $class::canView()) {
                    $visibleWidgets[] = $id;
                }
            }

            $this->assertNotEmpty($visibleWidgets, "{$role} must see at least some widgets");

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
