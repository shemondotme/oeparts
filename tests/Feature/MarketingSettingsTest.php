<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\MarketingSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MarketingSettings merges IntegrationsSettings ('integrations'),
 * NewsletterSettings ('newsletter'), and the Rush Processing Upsell fields
 * extracted from Checkout ('rush_upsell', new group). This confirms the
 * cross-group save routing, and — since the rush-upsell extraction is a
 * real customer-facing checkout-flow data migration, not just an admin-UI
 * move — that the group-rename migration itself preserves an
 * operator-customized value rather than silently resetting it.
 */
class MarketingSettingsTest extends TestCase
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
    public function saving_edits_across_the_three_merged_groups_writes_each_to_its_own_row(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(MarketingSettings::class)
            ->set('data.gtm_id', 'GTM-TESTID')
            ->set('data.rate_limit_per_hour', 25)
            ->set('data.urgent_processing_fee', 19.99)
            ->call('save');

        $this->assertSame('GTM-TESTID', Setting::where('group', 'integrations')->where('key', 'gtm_id')->value('value'));
        $this->assertSame('25', Setting::where('group', 'newsletter')->where('key', 'rate_limit_per_hour')->value('value'));
        $this->assertSame('19.99', Setting::where('group', 'rush_upsell')->where('key', 'urgent_processing_fee')->value('value'));
    }

    #[Test]
    public function untouched_page_reports_no_changes(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(MarketingSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }

    #[Test]
    public function reset_to_defaults_restores_across_multiple_groups(): void
    {
        Setting::updateOrCreate(['group' => 'newsletter', 'key' => 'double_opt_in'], ['value' => 'false', 'type' => 'boolean']);
        Setting::updateOrCreate(['group' => 'rush_upsell', 'key' => 'urgent_processing_fee'], ['value' => '999.00', 'type' => 'string']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(MarketingSettings::class)
            ->call('resetToDefaults')
            ->call('confirmReset')
            ->call('save');

        // double_opt_in is a boolean field — persistChanges() always
        // normalizes the stored value to the literal string 'true'/'false'
        // on save regardless of how the seeder itself spelled it ('1'/'0'),
        // so compare the boolean meaning, not the raw string.
        $seededDoubleOptIn = collect(SettingsSeeder::definitions())->where('group', 'newsletter')->firstWhere('key', 'double_opt_in')['value'];
        $this->assertSame(
            filter_var($seededDoubleOptIn, FILTER_VALIDATE_BOOLEAN),
            filter_var(Setting::where('group', 'newsletter')->where('key', 'double_opt_in')->value('value'), FILTER_VALIDATE_BOOLEAN)
        );
        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->where('group', 'rush_upsell')->firstWhere('key', 'urgent_processing_fee')['value'],
            Setting::where('group', 'rush_upsell')->where('key', 'urgent_processing_fee')->value('value')
        );
    }

    #[Test]
    public function no_two_of_the_three_merged_groups_collide_on_a_key_name(): void
    {
        $groups = ['integrations', 'newsletter', 'rush_upsell'];
        $expectedTotal = collect(SettingsSeeder::definitions())->whereIn('group', $groups)->count();

        $method = new \ReflectionMethod(MarketingSettings::class, 'getFactoryDefaults');
        $method->setAccessible(true);
        $defaults = $method->invoke(new MarketingSettings());

        $this->assertCount($expectedTotal, $defaults);
    }

    #[Test]
    public function the_rush_upsell_group_rename_migration_preserves_an_operator_customized_value(): void
    {
        // Simulate the pre-migration state: an operator-set value sitting
        // under the old checkout.urgent_processing_fee key, as if this
        // migration had not yet run.
        Setting::where('group', 'rush_upsell')->whereIn('key', [
            'urgent_processing_enabled', 'urgent_processing_fee', 'urgent_processing_label', 'urgent_processing_description',
        ])->delete();
        Setting::create(['group' => 'checkout', 'key' => 'urgent_processing_fee', 'value' => '42.50', 'type' => 'string']);
        Setting::create(['group' => 'checkout', 'key' => 'urgent_processing_enabled', 'value' => 'true', 'type' => 'boolean']);

        $migration = require database_path('migrations/2026_08_15_000003_move_rush_upsell_settings_to_own_group.php');
        $migration->up();

        $this->assertSame('42.50', Setting::where('group', 'rush_upsell')->where('key', 'urgent_processing_fee')->value('value'));
        $this->assertSame('true', Setting::where('group', 'rush_upsell')->where('key', 'urgent_processing_enabled')->value('value'));
        $this->assertDatabaseMissing('settings', ['group' => 'checkout', 'key' => 'urgent_processing_fee']);
        $this->assertDatabaseMissing('settings', ['group' => 'checkout', 'key' => 'urgent_processing_enabled']);
    }

    #[Test]
    public function the_migration_down_restores_rows_to_the_checkout_group(): void
    {
        $migration = require database_path('migrations/2026_08_15_000003_move_rush_upsell_settings_to_own_group.php');
        $migration->down();

        $this->assertSame(
            '9.99',
            Setting::where('group', 'checkout')->where('key', 'urgent_processing_fee')->value('value')
        );
        $this->assertDatabaseMissing('settings', ['group' => 'rush_upsell', 'key' => 'urgent_processing_fee']);
    }

    protected function tearDown(): void
    {
        Cache::forget('settings.checkout');
        Cache::forget('settings.rush_upsell');

        parent::tearDown();
    }
}
