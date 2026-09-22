<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\SettingsService;
use App\Support\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $response->assertSee('href="'.url('/en/warranty-info').'"', false);
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
        $enabled->assertSee('href="'.url('/en/faq').'"', false);

        app(SettingsService::class)->set('menu.footer_show_faq', '0');

        $disabled = $this->get('/en/');
        $disabled->assertOk();
        $disabled->assertDontSee('href="'.url('/en/faq').'"', false);
    }

    /**
     * Phase 14 (Monitoring/Logging Audit). items()'s catch used to be
     * inside the rememberForever() callback and completely silent — this
     * pins both fixes: a warning is now logged, and the failure itself is
     * never cached (asserted directly via Cache::has(), which is the actual
     * invariant that matters — rememberForever() only skips recomputing a
     * key it successfully wrote). Forces a genuine query failure by
     * dropping menu_items (the eager-loaded relation, not Schema::hasTable-
     * checked) while a real menus row exists — dropping a *column* Eloquent
     * itself queries by does NOT reliably throw against SQLite here: its
     * grammar double-quotes identifiers, and SQLite's well-known fallback
     * silently reinterprets an unresolvable double-quoted identifier as a
     * string literal instead of erroring, so the query just matches zero
     * rows rather than failing. A genuinely missing TABLE (not "not
     * created yet", which is the expected Schema::hasTable() path already
     * handled without logging) doesn't have that escape hatch.
     */
    #[Test]
    public function a_broken_menus_query_logs_a_warning_and_is_not_cached_forever(): void
    {
        Log::spy();
        Menu::create(['name' => 'Header EN', 'location' => 'header', 'lang' => 'en', 'is_active' => true]);
        Schema::drop('menu_items');

        $this->assertNull(MenuRegistry::items('header', 'en'));
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'MenuRegistry::items'));
        $this->assertFalse(Cache::has('menus.header.en'));
    }
}
