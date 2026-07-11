<?php

namespace Tests\Feature;

use App\Filament\Pages\System\SetupAssistant;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for §5q #11: SetupAssistant's "Clear Cache" was a
 * forbidden Cache::flush() (rule #5 — kills sessions on production Redis)
 * with modal copy claiming it cleared framework caches, and its maintenance
 * toggle drove `artisan down` — a second maintenance system, disconnected
 * from the settings-based MaintenanceMode middleware, whose 503 also locks
 * the operator out of the panel.
 */
class SetupAssistantSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function clear_caches_never_flushes_the_application_cache_store(): void
    {
        Cache::put('session-critical-key', 'still-here', 600);

        Livewire::test(SetupAssistant::class)->call('clearCache');

        $this->assertSame(
            'still-here',
            Cache::get('session-critical-key'),
            'clearCache() must not touch the application cache store (rule #5)',
        );
    }

    #[Test]
    public function maintenance_toggle_drives_the_settings_flag_not_artisan_down(): void
    {
        $this->assertFalse((bool) settings('maintenance.enabled', false));

        Livewire::test(SetupAssistant::class)->call('toggleMaintenance');

        app(\App\Services\SettingsService::class)->forget('maintenance');
        $this->assertTrue((bool) settings('maintenance.enabled', false), 'toggle must enable the Module 19 settings flag');
        $this->assertFalse(
            File::exists(storage_path('framework/down')),
            'toggle must never create the artisan-down file — it would 503 the admin panel itself',
        );

        Livewire::test(SetupAssistant::class)->call('toggleMaintenance');

        app(\App\Services\SettingsService::class)->forget('maintenance');
        $this->assertFalse((bool) settings('maintenance.enabled', false), 'second toggle must disable it again');
    }

    #[Test]
    public function setup_assistant_is_reachable_from_navigation(): void
    {
        $this->assertTrue(
            SetupAssistant::shouldRegisterNavigation(),
            'the onboarding surface must be listed in the System cluster nav, not hidden behind one Health Check link',
        );

        $this->get(SetupAssistant::getUrl())->assertSuccessful();
    }
}
