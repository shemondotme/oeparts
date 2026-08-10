<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MenuResource/MenuItem was a fully-built admin feature (CRUD, ordering,
 * nesting, CMS-page linking) with no code anywhere reading it — navbar and
 * footer both built their nav from a hardcoded array, so building a menu in
 * the admin had zero effect on the storefront.
 */
class MenuRegistryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function default_hardcoded_nav_renders_when_no_menu_is_configured(): void
    {
        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('Brands', false);
    }

    #[Test]
    public function a_configured_header_menu_replaces_the_default_nav(): void
    {
        $menu = Menu::create(['name' => 'Header EN', 'location' => 'header', 'lang' => 'en', 'is_active' => true]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => ['en' => 'Custom Nav Item'],
            'type' => 'url', 'url' => '/en/custom-page', 'sort_order' => 1, 'target' => '_self',
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('Custom Nav Item');
    }

    #[Test]
    public function an_inactive_menu_does_not_override_the_default_nav(): void
    {
        $menu = Menu::create(['name' => 'Header EN', 'location' => 'header', 'lang' => 'en', 'is_active' => false]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => ['en' => 'Should Not Appear'],
            'type' => 'url', 'url' => '/en/hidden', 'sort_order' => 1, 'target' => '_self',
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertDontSee('Should Not Appear');
    }

    #[Test]
    public function a_page_type_menu_item_resolves_to_the_pages_url(): void
    {
        $page = Page::create([
            'title' => ['en' => 'Warranty Info'], 'slug' => 'warranty-info',
            'content' => ['en' => 'x'], 'status' => ContentStatus::Published,
            'created_by' => Admin::factory()->create()->id,
        ]);
        $menu = Menu::create(['name' => 'Header EN', 'location' => 'header', 'lang' => 'en', 'is_active' => true]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => ['en' => 'Warranty'],
            'type' => 'page', 'page_id' => $page->id, 'sort_order' => 1, 'target' => '_self',
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('href="' . url('/en/warranty-info') . '"', false);
    }

    #[Test]
    public function a_configured_footer_menu_appends_links_to_the_footer(): void
    {
        $menu = Menu::create(['name' => 'Footer EN', 'location' => 'footer', 'lang' => 'en', 'is_active' => true]);
        MenuItem::create([
            'menu_id' => $menu->id, 'label' => ['en' => 'Custom Footer Link'],
            'type' => 'url', 'url' => '/en/careers', 'sort_order' => 1, 'target' => '_self',
        ]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('Custom Footer Link');
    }

    #[Test]
    public function footer_show_toggles_hide_their_respective_links(): void
    {
        // Baseline: the FAQ footer link only exists because this fix wires
        // it up at all — confirms it's actually present before disabling it.
        $enabled = $this->get('/en/');
        $enabled->assertOk();
        $enabled->assertSee('href="' . url('/en/faq') . '"', false);

        app(\App\Services\SettingsService::class)->set('menu.footer_show_faq', '0');

        $disabled = $this->get('/en/');
        $disabled->assertOk();
        $disabled->assertDontSee('href="' . url('/en/faq') . '"', false);
    }
}
