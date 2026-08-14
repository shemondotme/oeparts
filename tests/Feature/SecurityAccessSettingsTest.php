<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SecurityAccessSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SecurityAccessSettings merges the old AuthSettings ('auth' group) and
 * SecuritySettings ('security' group) pages into one 2-tab page, following
 * SeoControlCenter's $settingsGroups multi-group override pattern. This
 * confirms a single save() call correctly routes each field back to its
 * own group's Setting row — not just that the page loads without error
 * (already covered generically by SettingsPageNoPhantomChangesTest /
 * SettingsPageBackActionTest, which iterate every registered page).
 */
class SecurityAccessSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    #[Test]
    public function saving_edits_both_tabs_writes_each_field_to_its_own_group(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SecurityAccessSettings::class)
            ->set('data.otp_max_attempts', 7)
            ->set('data.login_max_attempts', 12)
            ->call('save');

        $this->assertSame('7', Setting::where('group', 'auth')->where('key', 'otp_max_attempts')->value('value'));
        $this->assertSame('12', Setting::where('group', 'security')->where('key', 'login_max_attempts')->value('value'));
    }

    #[Test]
    public function untouched_page_reports_no_changes(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SecurityAccessSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }

    #[Test]
    public function reset_to_defaults_restores_both_groups(): void
    {
        Setting::updateOrCreate(['group' => 'auth', 'key' => 'otp_max_attempts'], ['value' => '9', 'type' => 'integer']);
        Setting::updateOrCreate(['group' => 'security', 'key' => 'login_max_attempts'], ['value' => '99', 'type' => 'integer']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SecurityAccessSettings::class)
            ->call('resetToDefaults')
            ->call('confirmReset')
            ->call('save');

        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->firstWhere('key', 'otp_max_attempts')['value'],
            Setting::where('group', 'auth')->where('key', 'otp_max_attempts')->value('value')
        );
        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->firstWhere('key', 'login_max_attempts')['value'],
            Setting::where('group', 'security')->where('key', 'login_max_attempts')->value('value')
        );
    }
}
