<?php

namespace Tests\Feature;

use App\Filament\Pages\System\UpdateHistoryPage;
use App\Models\Admin;
use App\Models\UpdateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update History page (Module 21, Chunk 3.6) — the update audit trail.
 */
class UpdateHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\SettingsSeeder::class, \Database\Seeders\RolesSeeder::class]);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function a_viewer_sees_the_update_history(): void
    {
        UpdateHistory::create([
            'from_version' => '1.0.1', 'to_version' => '1.1.0',
            'status' => UpdateHistory::STATUS_ROLLED_BACK, 'step' => 'verify',
            'started_at' => now(), 'finished_at' => now(), 'meta' => [],
        ]);

        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(UpdateHistoryPage::getUrl())
            ->assertSuccessful()
            ->assertSee('Update History')
            ->assertSee('1.1.0');
    }

    #[Test]
    public function a_role_without_view_updates_is_forbidden(): void
    {
        $this->actingAs($this->adminWithRole('support'), 'admin');

        $this->get(UpdateHistoryPage::getUrl())->assertForbidden();
    }
}
