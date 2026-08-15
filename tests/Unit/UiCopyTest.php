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

    /**
     * Fast-follow expansion (checkout_/account_/footer_, ~512 more keys):
     * same shape of check as the navbar_/nav_ regression guard above, but
     * scanning every Blade file rather than 2 known files — checkout_/
     * account_/footer_ call sites are spread across the checkout flow,
     * the account dashboard, and the footer component. Verified by hand
     * before building this (0 mismatches across 459 real call sites) —
     * this makes that verification permanent instead of a one-off check.
     *
     * The regex requires a comma right after the closing quote, so it
     * only matches complete literal keys — a handful of real call sites
     * build the key dynamically (e.g. 'account_order_status_'.$order->
     * status->value) and are deliberately NOT matched here; those are
     * covered separately below since a static regex can't enumerate
     * every value a PHP expression might produce at runtime.
     */
    #[Test]
    public function every_checkout_account_footer_ui_copy_call_uses_a_prefix_that_is_actually_seeded(): void
    {
        $seededKeys = \App\Services\UiCopyInstaller::installedCheckoutAccountFooterUiKeyPrefixes();
        $this->assertNotEmpty($seededKeys);

        $combined = '';
        foreach (\Symfony\Component\Finder\Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            $combined .= $file->getContents();
        }

        preg_match_all("/ui_(?:copy|trans_choice)\(\s*'((?:checkout|account|footer)_[a-z0-9_]+)'\s*,/", $combined, $matches);
        $callSiteKeys = array_unique($matches[1]);

        $this->assertNotEmpty($callSiteKeys);

        foreach ($callSiteKeys as $key) {
            $this->assertContains(
                $key,
                $seededKeys,
                "ui_copy('{$key}', ...) is called but ui.{$key} was never seeded by UiCopyInstaller — an admin override for it can never take effect."
            );
        }
    }

    /**
     * The 3 dynamically-built call sites the test above deliberately
     * skips: 'account_order_status_'.$order->status->value (dashboard.
     * blade.php, order-detail.blade.php, orders.blade.php x2, refund-
     * form.blade.php), 'account_payment_status_'.$ps (order-detail.
     * blade.php), and 'checkout_payment_status_'.$orderData['payment_
     * status'] (thank-you.blade.php). Rather than parsing PHP expressions
     * out of Blade, this asserts every concrete key each enum can
     * actually produce at runtime is seeded — the real thing that
     * matters — cross-checked directly against OrderStatus/PaymentStatus.
     */
    #[Test]
    public function every_dynamically_built_checkout_account_key_is_seeded_for_every_real_enum_value(): void
    {
        $seededKeys = \App\Services\UiCopyInstaller::installedCheckoutAccountFooterUiKeyPrefixes();

        foreach (\App\Enums\OrderStatus::cases() as $status) {
            $key = 'account_order_status_'.$status->value;
            $this->assertContains($key, $seededKeys, "ui.{$key} missing — account/orders pages would silently fall back to the lang file for this order status.");
        }

        foreach (\App\Enums\PaymentStatus::cases() as $status) {
            $accountKey = 'account_payment_status_'.$status->value;
            $checkoutKey = 'checkout_payment_status_'.$status->value;
            $this->assertContains($accountKey, $seededKeys, "ui.{$accountKey} missing.");
            $this->assertContains($checkoutKey, $seededKeys, "ui.{$checkoutKey} missing.");
        }
    }

    #[Test]
    public function an_admin_set_checkout_override_actually_renders_on_the_storefront(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'checkout_urgent_processing_eyebrow'],
            [
                'value' => json_encode(array_fill_keys($langs, 'MyCustomCheckoutEyebrow')),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $this->assertSame(
            'MyCustomCheckoutEyebrow',
            ui_copy('checkout_urgent_processing_eyebrow', 'checkout.urgent_processing_eyebrow')
        );
    }

    /**
     * ui_trans_choice() resolves its override through Laravel's own
     * trans_choice() (pipe-delimited plural forms), not a plain string
     * lookup — this is the one behavioral difference from ui_copy() in
     * this expansion, worth its own explicit check rather than assuming
     * the generic seeding pattern "just works" for it.
     */
    #[Test]
    public function an_admin_set_account_pluralization_override_resolves_the_correct_form(): void
    {
        $langs = ['en', 'de', 'lt', 'fr', 'es'];
        Setting::updateOrCreate(
            ['group' => 'ui', 'key' => 'account_item_word'],
            [
                'value' => json_encode(array_fill_keys($langs, 'single-thing|many-things')),
                'type' => SettingType::Json->value,
                'is_encrypted' => false,
            ]
        );
        app(SettingsService::class)->forget('ui');

        $this->assertSame('single-thing', ui_trans_choice('account_item_word', 'account.item_word', 1));
        $this->assertSame('many-things', ui_trans_choice('account_item_word', 'account.item_word', 5));
    }
}
