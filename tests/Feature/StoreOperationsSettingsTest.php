<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StoreOperationsSettings is the largest merge in the settings reorg — 12
 * groups (orders/customers/cart/dashboard/shipping/tax/checkout/payment/
 * email/contact/part_inquiry/invoice) behind one page. This confirms a
 * single save() call correctly routes fields from DIFFERENT original
 * groups to their own Setting rows, that none of the 12 groups collide on
 * a key name, and that the 3 header actions carried forward from
 * PaymentSettings/EmailSettings (Test Airwallex / Test Paysera / Send Test
 * Email) survived the merge into one combined getHeaderActions() override.
 */
class StoreOperationsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const GROUPS = [
        'orders', 'customers', 'cart', 'dashboard', 'shipping', 'tax',
        'checkout', 'payment', 'email', 'contact', 'part_inquiry', 'invoice',
    ];

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
    public function no_two_of_the_twelve_merged_groups_collide_on_a_key_name(): void
    {
        $expectedTotal = collect(SettingsSeeder::definitions())->whereIn('group', self::GROUPS)->count();

        $method = new \ReflectionMethod(StoreOperationsSettings::class, 'getFactoryDefaults');
        $method->setAccessible(true);
        $defaults = $method->invoke(new StoreOperationsSettings());

        $this->assertCount(
            $expectedTotal,
            $defaults,
            'Two of the 12 merged groups share a key name — getFactoryDefaults() silently dropped one via mapWithKeys().'
        );
    }

    #[Test]
    public function saving_edits_across_several_different_original_groups_writes_each_to_its_own_row(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->set('data.order_number_prefix', 'PO')
            ->set('data.handling_fee', 4.5)
            ->set('data.timeout_minutes', 45)
            ->set('data.payment_terms_days', 60)
            ->call('save');

        $this->assertSame('PO', Setting::where('group', 'orders')->where('key', 'order_number_prefix')->value('value'));
        $this->assertSame('4.5', Setting::where('group', 'shipping')->where('key', 'handling_fee')->value('value'));
        $this->assertSame('45', Setting::where('group', 'checkout')->where('key', 'timeout_minutes')->value('value'));
        $this->assertSame('60', Setting::where('group', 'invoice')->where('key', 'payment_terms_days')->value('value'));
    }

    #[Test]
    public function untouched_page_reports_no_changes(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }

    #[Test]
    public function reset_to_defaults_restores_across_multiple_groups(): void
    {
        Setting::updateOrCreate(['group' => 'orders', 'key' => 'order_number_prefix'], ['value' => 'XXX', 'type' => 'string']);
        Setting::updateOrCreate(['group' => 'invoice', 'key' => 'payment_terms_days'], ['value' => '999', 'type' => 'integer']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->call('resetToDefaults')
            ->call('confirmReset')
            ->call('save');

        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->where('group', 'orders')->firstWhere('key', 'order_number_prefix')['value'],
            Setting::where('group', 'orders')->where('key', 'order_number_prefix')->value('value')
        );
        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->where('group', 'invoice')->firstWhere('key', 'payment_terms_days')['value'],
            Setting::where('group', 'invoice')->where('key', 'payment_terms_days')->value('value')
        );
    }

    #[Test]
    public function the_three_merged_header_actions_survived_the_merge(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(StoreOperationsSettings::class)
            ->assertActionExists('testAirwallex')
            ->assertActionExists('testPaysera')
            ->assertActionExists('testEmail');
    }

    #[Test]
    public function the_dead_orders_urgent_processing_rows_no_longer_exist(): void
    {
        // Rush Processing Upsell itself moved to MarketingSettings in Phase 5
        // (group 'checkout' -> 'rush_upsell') — see MarketingSettingsTest for
        // its save-routing coverage. This just confirms the Phase 4 dead-row
        // retirement migration still holds after that move.
        $this->assertDatabaseMissing('settings', ['group' => 'orders', 'key' => 'urgent_processing_enabled']);
        $this->assertDatabaseMissing('settings', ['group' => 'orders', 'key' => 'urgent_processing_fee']);
    }
}
