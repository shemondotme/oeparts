<?php

namespace Tests\Feature;

use App\Livewire\SidebarRecentNav;
use App\Models\Admin;
use App\Models\CarModel;
use App\Services\AdminNavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SidebarRecentNavTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);
    }

    private function activeAdmin(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function visiting_a_resource_edit_page_records_a_record_specific_recent_entry(): void
    {
        $admin = $this->activeAdmin('super_admin');
        $this->actingAs($admin, 'admin');

        $carModel = CarModel::factory()->create(['name' => 'Golf GTI']);

        $this->get("/admin/car-models/{$carModel->id}/edit")->assertOk();

        $recent = $admin->refresh()->dashboard_preferences['recent_nav'] ?? [];

        $this->assertCount(1, $recent);
        $this->assertSame(url("/admin/car-models/{$carModel->id}/edit"), $recent[0]['url']);
        $this->assertSame('Golf GTI', $recent[0]['label']);
    }

    #[Test]
    public function visiting_dashboard_and_create_pages_does_not_record_a_recent_entry(): void
    {
        $admin = $this->activeAdmin('super_admin');
        $this->actingAs($admin, 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/car-models/create')->assertOk();

        $recent = $admin->refresh()->dashboard_preferences['recent_nav'] ?? [];

        $this->assertEmpty($recent);
    }

    #[Test]
    public function recent_list_caps_at_eight_and_dedups_revisited_pages_to_the_front(): void
    {
        $admin = $this->activeAdmin('super_admin');
        $this->actingAs($admin, 'admin');

        $carModels = CarModel::factory()->count(9)->create();

        foreach ($carModels as $carModel) {
            $this->get("/admin/car-models/{$carModel->id}/edit")->assertOk();
        }

        // Revisit the 5th car model — should dedup and move to the front.
        $this->get("/admin/car-models/{$carModels[4]->id}/edit")->assertOk();

        $recent = $admin->refresh()->dashboard_preferences['recent_nav'] ?? [];

        $this->assertCount(AdminNavService::MAX_RECENT, $recent);
        $this->assertSame(url("/admin/car-models/{$carModels[4]->id}/edit"), $recent[0]['url']);
    }

    #[Test]
    public function sidebar_recent_nav_renders_seeded_entries(): void
    {
        $admin = $this->activeAdmin('super_admin');

        $carModel = CarModel::factory()->create(['name' => 'Bosch Brake Pad Set']);

        $url = url("/admin/car-models/{$carModel->id}/edit");

        AdminNavService::recordVisit($admin, $url, 'Bosch Brake Pad Set', $url, 'Catalog');

        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Livewire::test(SidebarRecentNav::class)->assertSee('Bosch Brake Pad Set');
    }

    #[Test]
    public function sidebar_recent_nav_drops_entries_for_resources_no_longer_visible_to_the_role(): void
    {
        // catalog_admin has no Faq permissions (per RolesSeeder) — FaqResource
        // does not appear in catalog_admin's filament()->getNavigation().
        $admin = $this->activeAdmin('catalog_admin');

        $url = url('/admin/faqs/1/edit');

        AdminNavService::recordVisit($admin, $url, 'Why genuine OEM?', $url, 'Content');

        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Livewire::test(SidebarRecentNav::class)->assertDontSee('Why genuine OEM?');
    }
}
