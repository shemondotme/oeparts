<?php

namespace Tests\Feature;

use App\Filament\Pages\System\SystemUpdates;
use App\Models\Admin;
use App\Models\UpdateHistory;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateChecker;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SystemUpdates one-click apply (Module 21, Chunk 3.5) — permission gate + password
 * re-auth + delegation to the apply FSM. The FSM itself is exercised in
 * UpdateApplierTest; here a fake applier isolates the page behaviour.
 */
class SystemUpdatesApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        config()->set('updates.check.catalog_url', 'https://updates.test/releases.json');
        Cache::forget(UpdateChecker::CACHE_KEY);

        Http::fake(['updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
            ['version' => '9.9.9', 'min_version_to_update_from' => '0.0.0', 'download_url' => 'https://x/oeparts.zip',
             'sha256' => str_repeat('a', 64), 'size_bytes' => 1000, 'migration_count' => 1],
        ]], 200), '*' => Http::response('', 500)]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** A fake applier so the page test never runs the real update pipeline. */
    private function fakeApplier(): void
    {
        app()->instance(UpdateApplier::class, new class extends UpdateApplier
        {
            public function start(array $manifest, ?int $initiatedBy = null): UpdateHistory
            {
                return UpdateHistory::create([
                    'from_version' => '1.0.1',
                    'to_version'   => $manifest['version'] ?? '9.9.9',
                    'status'       => UpdateHistory::STATUS_BACKING_UP,
                    'step'         => 'backup',
                    'initiated_by' => $initiatedBy,
                    'started_at'   => now(),
                    'meta'         => ['manifest' => $manifest, 'step_index' => 0],
                ]);
            }
        });
    }

    #[Test]
    public function a_role_without_apply_permission_cannot_start_an_update(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('view updates'); // can see the page, but not apply
        $this->actingAs($admin, 'admin');

        Livewire::test(SystemUpdates::class)->call('startApply')->assertForbidden();

        $this->assertSame(0, UpdateHistory::count());
    }

    #[Test]
    public function the_wrong_password_is_rejected(): void
    {
        $admin = Admin::factory()->create(['is_active' => true, 'password' => Hash::make('correct-horse')]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(SystemUpdates::class)
            ->set('applyPassword', 'wrong-password')
            ->call('startApply')
            ->assertHasErrors('applyPassword');

        $this->assertSame(0, UpdateHistory::count());
    }

    #[Test]
    public function the_correct_password_starts_the_apply_fsm(): void
    {
        $this->fakeApplier();

        $admin = Admin::factory()->create(['is_active' => true, 'password' => Hash::make('correct-horse')]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(SystemUpdates::class)
            ->set('applyPassword', 'correct-horse')
            ->call('startApply')
            ->assertSet('applying', true)
            ->assertHasNoErrors();

        $this->assertSame(1, UpdateHistory::count());
        $this->assertSame('9.9.9', UpdateHistory::first()->to_version);
    }
}
