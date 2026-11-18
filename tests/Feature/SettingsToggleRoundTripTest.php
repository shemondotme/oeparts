<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A switch turned off and saved is stored as the text "false"; reopening the page must show it OFF
 * (it used to come back ON, because the text "false" counts as true).
 */
class SettingsToggleRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function a_switch_saved_as_off_is_still_off_when_the_page_is_opened_again(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->set('data.sitemap_include_crossrefs', false)
            ->set('data.google_ping_enabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('false', Setting::where('group', 'seo')->where('key', 'sitemap_include_crossrefs')->value('value'));

        Livewire::test(SeoControlCenter::class)
            ->assertSet('data.sitemap_include_crossrefs', false)
            ->assertSet('data.google_ping_enabled', false)
            ->assertSet('data.sitemap_include_blog', true);
    }

    #[Test]
    public function a_switch_saved_as_off_reads_as_false_everywhere_the_shop_asks_for_it(): void
    {
        $service = app(\App\Services\SettingsService::class);

        foreach (['false', '0'] as $off) {
            $service->set('cart.coupon_enabled', $off);
            $this->assertFalse(settings('cart.coupon_enabled', true), "stored as {$off}");
            $this->assertFalse((bool) settings('cart.coupon_enabled', true), "stored as {$off}");
        }

        foreach (['true', '1'] as $on) {
            $service->set('cart.coupon_enabled', $on);
            $this->assertTrue(settings('cart.coupon_enabled', false), "stored as {$on}");
        }
    }

    #[Test]
    public function text_settings_are_left_alone(): void
    {
        app(\App\Services\SettingsService::class)->set('general.site_name', 'Plain Shop');

        $this->assertSame('Plain Shop', settings('general.site_name'));
    }
}
