<?php

namespace Tests\Unit;

use App\Enums\SettingType;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UiCopyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ui_copy_falls_back_to_lang_file_when_empty(): void
    {
        $this->assertStringContainsString('Genuine', ui_copy('cart_genuine_x_missing', 'cart.genuine_part'));
    }

    #[Test]
    public function ui_copy_uses_setting_when_set(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'search_test_unique'],
            [
                'value' => json_encode(array_fill_keys($langs, 'AAA'), JSON_UNESCAPED_UNICODE),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $this->assertSame('AAA', ui_copy('search_test_unique', 'search.unknown_brand'));
    }

    #[Test]
    public function ui_copy_replaces_placeholders(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'search_incl_vat_test'],
            [
                'value' => json_encode(array_fill_keys($langs, 'Incl. :rate% VAT')),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $this->assertStringContainsString('21', ui_copy('search_incl_vat_test', 'search.incl_vat', ['rate' => '21']));
    }

    /**
     * Regression guard for the navbar_/nav_ prefix mismatch (settings reorg
     * Phase 7.1): navbar.blade.php's ui_copy() call sites used to pass
     * 'navbar_cart_label' etc as the override key, but UiCopyInstaller
     * seeds every navbar.php string under a 'nav_' prefix — the two never
     * matched, so an admin-set override for e.g. the cart label could never
     * actually take effect on the storefront, silently falling through to
     * the lang-file default on every request. This asserts the two sides
     * now agree for every key UiCopyInstaller actually seeds from
     * navbar.php, not just one hand-picked example.
     */
    #[Test]
    public function every_navbar_blade_ui_copy_call_uses_a_prefix_that_is_actually_seeded(): void
    {
        // Not every seeded nav_* key has a call site (nav_strip_doc/
        // strip_genuine/strip_status are dead lang-file entries with zero
        // consumers anywhere, pre-existing and unrelated to this bug — not
        // fixed here, that's a separate "unused content" finding, not a
        // prefix mismatch). This test instead goes the other direction:
        // every call site that DOES exist must reference a key that was
        // actually seeded, catching the exact navbar_/nav_ mismatch bug
        // without asserting the (unrelated) inverse.
        $seededNavKeys = collect(\App\Services\UiCopyInstaller::installedUiKeyPrefixes())
            ->filter(fn (string $key) => str_starts_with($key, 'nav_'))
            ->all();

        $this->assertNotEmpty($seededNavKeys, 'Expected UiCopyInstaller to seed at least one nav_* key from lang/en/navbar.php.');

        $navbarBlade = file_get_contents(resource_path('views/components/navbar.blade.php'));
        $appBlade = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $combined = $navbarBlade . $appBlade;

        preg_match_all("/ui_copy\('(nav_[a-z_]+)'/", $combined, $matches);
        $callSiteKeys = array_unique($matches[1]);

        $this->assertNotEmpty($callSiteKeys);

        foreach ($callSiteKeys as $key) {
            $this->assertContains(
                $key,
                $seededNavKeys,
                "ui_copy('{$key}', ...) is called but ui.{$key} was never seeded by UiCopyInstaller — an admin override for it can never take effect."
            );
        }

        // The specific bug: no call site should still be asking for the
        // wrong 'navbar_' prefix.
        $this->assertStringNotContainsString("ui_copy('navbar_", $navbarBlade);
        $this->assertStringNotContainsString("ui_copy('navbar_", $appBlade);
    }

    #[Test]
    public function an_admin_set_nav_cart_label_override_actually_renders_on_the_storefront(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'nav_cart_label'],
            [
                'value' => json_encode(array_fill_keys($langs, 'MyCustomCartLabel')),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('MyCustomCartLabel');
    }
}
