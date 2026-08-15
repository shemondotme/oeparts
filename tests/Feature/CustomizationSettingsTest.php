<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\CustomizationSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CustomizationSettings is the largest merge in the settings reorg — 7
 * groups (ui/navbar/footer/announcement/sections/menu/social_links) behind
 * one page. This confirms a single save() call correctly routes fields
 * from DIFFERENT original groups to their own Setting rows (not just that
 * the page loads — see SettingsPageNoPhantomChangesTest /
 * SettingsPageBackActionTest for that generic coverage), and that no two
 * of the 7 groups collide on a key name (getFactoryDefaults()'s
 * mapWithKeys() would silently drop one half of a collision rather than
 * erroring).
 */
class CustomizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const GROUPS = ['ui', 'navbar', 'footer', 'announcement', 'sections', 'menu', 'social_links'];

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
    public function no_two_of_the_seven_merged_groups_collide_on_a_key_name(): void
    {
        $expectedTotal = collect(SettingsSeeder::definitions())->whereIn('group', self::GROUPS)->count();

        $method = new \ReflectionMethod(CustomizationSettings::class, 'getFactoryDefaults');
        $method->setAccessible(true);
        $defaults = $method->invoke(new CustomizationSettings());

        $this->assertCount(
            $expectedTotal,
            $defaults,
            'Two of the 7 merged groups (ui/navbar/footer/announcement/sections/menu/social_links) share a key name — getFactoryDefaults() silently dropped one via mapWithKeys().'
        );
    }

    #[Test]
    public function saving_edits_across_three_different_original_groups_writes_each_to_its_own_row(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(CustomizationSettings::class)
            ->set('data.cart_label', 'Basket')
            ->set('data.oem_badge_text', 'Genuine Parts')
            ->set('data.testimonials_limit', 9)
            ->set('data.facebook_url', 'https://facebook.com/oeparts')
            ->call('save');

        $this->assertSame('Basket', Setting::where('group', 'navbar')->where('key', 'cart_label')->value('value'));
        $this->assertSame('Genuine Parts', Setting::where('group', 'footer')->where('key', 'oem_badge_text')->value('value'));
        $this->assertSame('9', Setting::where('group', 'sections')->where('key', 'testimonials_limit')->value('value'));
        $this->assertSame('https://facebook.com/oeparts', Setting::where('group', 'social_links')->where('key', 'facebook_url')->value('value'));
    }

    #[Test]
    public function untouched_page_reports_no_changes(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(CustomizationSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }

    #[Test]
    public function the_disabled_menus_repeater_never_writes_a_menu_menus_setting_row(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(CustomizationSettings::class)
            ->set('data.footer_show_about', false)
            ->call('save');

        $this->assertSame('false', Setting::where('group', 'menu')->where('key', 'footer_show_about')->value('value'));
        $this->assertDatabaseMissing('settings', ['group' => 'menu', 'key' => 'menus']);
    }

    #[Test]
    public function reset_to_defaults_restores_across_multiple_groups(): void
    {
        Setting::updateOrCreate(['group' => 'sections', 'key' => 'testimonials_limit'], ['value' => '99', 'type' => 'integer']);
        Setting::updateOrCreate(['group' => 'navbar', 'key' => 'cart_label'], ['value' => 'Something Else', 'type' => 'string']);
        // footer was one of the 3 real seed-row gaps closed in Phase 2
        // (had fields but zero SettingsSeeder rows, so Reset silently
        // no-op'd) — re-verified live here as part of Phase 8 hardening.
        Setting::updateOrCreate(['group' => 'footer', 'key' => 'oem_badge_text'], ['value' => 'Something Else', 'type' => 'string']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(CustomizationSettings::class)
            ->call('resetToDefaults')
            ->call('confirmReset')
            ->call('save');

        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->where('group', 'sections')->firstWhere('key', 'testimonials_limit')['value'],
            Setting::where('group', 'sections')->where('key', 'testimonials_limit')->value('value')
        );
        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->where('group', 'navbar')->firstWhere('key', 'cart_label')['value'],
            Setting::where('group', 'navbar')->where('key', 'cart_label')->value('value')
        );
        $this->assertSame(
            (string) collect(SettingsSeeder::definitions())->where('group', 'footer')->firstWhere('key', 'oem_badge_text')['value'],
            Setting::where('group', 'footer')->where('key', 'oem_badge_text')->value('value')
        );
    }
}
