<?php

namespace Tests\Feature;

use App\Filament\Pages\System\SystemUpdates;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\Updates\UpdateChecker;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update Settings panel (this redesign) — release channel + auto-apply-security,
 * DB-editable via settings()/SettingsService, with config/updates.php as the
 * fallback default. See SystemUpdates::saveUpdateSettings()/toggleAutoApplySecurity().
 */
class SystemUpdatesSettingsTest extends TestCase
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
        config()->set('updates.check.manifest_url', 'https://updates.test/version.json');
        Cache::forget(UpdateChecker::CACHE_KEY);

        Http::fake([
            'updates.test/*' => Http::response(['channel' => 'stable', 'releases' => [
                ['version' => '0.0.1'],
            ]], 200),
            '*' => Http::response('', 500),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function super_admin_sees_current_channel_and_auto_apply_settings_prefilled(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(SystemUpdates::class)
            ->assertSet('settingsChannel', 'stable')
            ->assertSet('settingsAutoApplySecurity', false);
    }

    #[Test]
    public function saving_update_settings_persists_to_the_settings_table(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(SystemUpdates::class)
            ->set('settingsChannel', 'beta')
            ->set('settingsAutoApplySecurity', true)
            ->call('saveUpdateSettings')
            ->assertHasNoErrors();

        $this->assertSame('beta', Setting::where('group', 'updates')->where('key', 'channel')->first()?->value);
        $this->assertSame('1', Setting::where('group', 'updates')->where('key', 'auto_apply_security')->first()?->value);
        $this->assertSame('beta', settings('updates.channel'));
        $this->assertTrue((bool) settings('updates.auto_apply_security'));
    }

    #[Test]
    public function toggle_auto_apply_security_flips_the_property_only_when_permitted(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(SystemUpdates::class)
            ->call('toggleAutoApplySecurity')
            ->assertSet('settingsAutoApplySecurity', true)
            ->call('toggleAutoApplySecurity')
            ->assertSet('settingsAutoApplySecurity', false);
    }

    #[Test]
    public function saving_update_settings_requires_apply_permission(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->givePermissionTo('view updates'); // can see the page, but not apply/change settings
        $this->actingAs($admin, 'admin');

        Livewire::test(SystemUpdates::class)
            ->set('settingsChannel', 'beta')
            ->call('saveUpdateSettings')
            ->assertForbidden();

        // Seeded default, unchanged — the forbidden call never reached SettingsService::set().
        $this->assertSame('stable', Setting::where('group', 'updates')->where('key', 'channel')->first()?->value);
    }

    #[Test]
    public function invalid_channel_value_is_rejected(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(SystemUpdates::class)
            ->set('settingsChannel', 'nightly')
            ->call('saveUpdateSettings')
            ->assertHasErrors(['settingsChannel']);
    }
}
