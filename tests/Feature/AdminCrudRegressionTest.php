<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\ManufacturerResource\Pages\CreateManufacturer;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\RedirectResource\Pages\ListRedirects;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression guard for two severe admin-panel bugs found during a full
 * manual QA pass (2026-08-16), both confirmed live via a real browser, not
 * just synthetic reproduction:
 *
 * 1. Creating a Customer via the admin panel (CustomerResource) always
 *    500'd — the form has no password field (customers are meant to sign
 *    in via the existing "Send Password Reset" action, the same pattern
 *    SocialAuthController already uses for social-login signups), but
 *    CreateCustomer never set one before insert, and users.password is
 *    NOT NULL with no default. Every single admin-created customer failed
 *    outright with a raw SQLSTATE error.
 *
 * 2. Category/Manufacturer/BlogPost/Page creation was silently broken for
 *    real users relying on the advertised slug auto-fill (all four share
 *    AdminUi::translatableTabs()'s slugSync feature, and all mark their
 *    'slug' field ->required()). The sync fired via ->live(onBlur: true)
 *    — but the field that loses focus is usually blurred BY the click on
 *    the Create button itself, so the async round trip that sets the slug
 *    races that same click's synchronous native HTML5 validation. The
 *    required slug input is still empty at the instant the browser
 *    validates it, so the browser silently blocks submission with no
 *    visible Filament error — confirmed live: typing a category/
 *    manufacturer name and clicking Create did *nothing*, over and over,
 *    with a 200 Livewire response and zero DB row, until the trigger was
 *    changed to a plain debounce that fires while still typing.
 *
 * 3. AdminUi::exportCsvBulkAction() cast every cell to (string) $cell
 *    before writing it into the CSV. A column accessor like
 *    'manufacturer.name' resolves through data_get() to a translatable
 *    array-cast attribute (Product/Manufacturer names are {locale =>
 *    value} JSON, never a plain string) — (string) on an array throws
 *    "Array to string conversion", which this app's local-env error
 *    reporting escalates to a fatal ErrorException. Every "Export
 *    Products" click 500'd outright, confirmed live via a real bulk
 *    export attempt on the Products table.
 *
 * 4. store.currency_position carried a legacy 'left' value (pre-dating a
 *    fix to SettingsSeeder's default, already noted in the seeder's own
 *    comment but never carried forward into a migration for installations
 *    seeded before that fix — this demo database included). The Select
 *    field only ever declared 'before'/'after' options, and
 *    GeneralBrandSettings::save() validates its whole multi-tab form
 *    together — so this one stale, unrelated value on the Regional
 *    Defaults tab silently blocked saving *any* change anywhere on the
 *    page, confirmed live: editing the Site Identity tab's Site Name and
 *    clicking Save did nothing, jumping to Regional Defaults with "The
 *    selected symbol Position is invalid."
 *
 * 5. The same exportCsvBulkAction() (string) $cell cast as #3, a different
 *    trigger: a backed enum (Redirect::type, RedirectType) doesn't
 *    implement Stringable either, found while building the Redirects CSV
 *    importer — every "Export Redirects" click 500'd the same way.
 */
class AdminCrudRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    #[Test]
    public function creating_a_customer_from_the_admin_panel_succeeds_with_a_hashed_random_password(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'QA Test Customer',
                'email' => 'qa-regression-customer@example.com',
                'phone' => '+49 30 55501234',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = User::where('email', 'qa-regression-customer@example.com')->first();
        $this->assertNotNull($customer, 'Admin-panel customer creation failed.');
        $this->assertTrue(str_starts_with($customer->password, '$2y$'), 'password must be a real bcrypt hash, not blank/plaintext');
    }

    #[Test]
    public function category_slug_autofill_still_populates_the_required_slug_field_from_the_name(): void
    {
        // This exercises the underlying afterStateUpdated logic (Str::slug()
        // + blank-target check), which is what actually broke nothing here —
        // the real bug was the ->live(onBlur: true) VS VS a same-click native
        // form submit, a browser-timing race that only reproduces through a
        // real DOM (confirmed via Playwright, not reproducible at this
        // Livewire::test level, since ->set() bypasses actual blur/click
        // event choreography entirely). This test guards the slug-generation
        // logic itself from regressing, not the trigger mode.
        Livewire::test(CreateCategory::class)
            ->set('data.name.en', 'Brake Parts And Discs')
            ->assertSet('data.slug', 'brake-parts-and-discs')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Category::where('slug', 'brake-parts-and-discs')->exists());
    }

    #[Test]
    public function manufacturer_slug_autofill_still_populates_the_required_slug_field_from_the_name(): void
    {
        Livewire::test(CreateManufacturer::class)
            ->set('data.name.en', 'Continental AG')
            ->assertSet('data.slug', 'continental-ag')
            ->fillForm(['country_code' => 'DE'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(Manufacturer::where('slug', 'continental-ag')->exists());
    }

    #[Test]
    public function exporting_products_to_csv_does_not_crash_on_translatable_relationship_columns(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => ['en' => 'Bosch', 'de' => 'Bosch']]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id, 'condition_id' => $condition->id]);

        Livewire::test(ListProducts::class)
            ->loadTable()
            ->callTableBulkAction('exportCsv', [$product])
            ->assertOk();
    }

    #[Test]
    public function exporting_redirects_to_csv_does_not_crash_on_the_backed_enum_type_column(): void
    {
        // A backed enum (RedirectType) doesn't implement Stringable — the
        // SAME (string) $cell cast bug as translatable-array columns
        // above, just triggered by a different cast type. Confirmed live:
        // (string) RedirectType::Permanent throws "Object of class
        // App\Enums\RedirectType could not be converted to string",
        // crashing every "Export Redirects" click outright.
        $redirect = Redirect::create(['from_url' => 'old-page', 'to_url' => '/new-page', 'type' => RedirectType::Permanent, 'is_active' => true]);

        Livewire::test(ListRedirects::class)
            ->loadTable()
            ->callTableBulkAction('exportCsv', [$redirect])
            ->assertOk();
    }

    #[Test]
    public function the_legacy_currency_position_migration_normalizes_the_stale_value(): void
    {
        Setting::where('group', 'store')->where('key', 'currency_position')->delete();
        Setting::create(['group' => 'store', 'key' => 'currency_position', 'value' => 'left', 'type' => 'string']);

        $migration = require database_path('migrations/2026_08_16_000001_fix_legacy_store_currency_position_value.php');
        $migration->up();

        $this->assertSame('after', Setting::where('group', 'store')->where('key', 'currency_position')->value('value'));
    }

    #[Test]
    public function general_brand_settings_saves_successfully_with_the_normalized_currency_position(): void
    {
        Setting::where('group', 'store')->where('key', 'currency_position')->update(['value' => 'after']);
        Cache::forget('settings.general');
        Cache::forget('settings.store');

        Livewire::test(\App\Filament\Pages\Settings\GeneralBrandSettings::class)
            ->set('data.site_name', 'OeParts Regression Test')
            ->call('save')
            ->assertHasNoErrors();

        Cache::forget('settings.general');
        $this->assertSame('OeParts Regression Test', settings('general.site_name'));
    }

    /**
     * checkout.payment_success_message/payment_error_message were seeded via
     * $ml() (identical English text duplicated into every locale) despite
     * being read through settings_trans() and rendered as a translatable
     * field — the mismatch between JSON-shaped data and a plain
     * single-locale Textarea made Alpine stringify the raw {locale => text}
     * object into the literal text "[object Object]" in the admin UI,
     * confirmed live. Migration 2026_07_11_000002 had already fixed this
     * once for already-seeded installs, but SettingsSeeder.php was never
     * updated to match, so any full reseed since then silently regressed
     * both rows back to English-only — this new migration re-applies the
     * same translations idempotently, and the seeder itself is now fixed.
     */
    #[Test]
    public function the_checkout_message_translations_migration_normalizes_locale_blind_rows(): void
    {
        Setting::where('group', 'checkout')->whereIn('key', ['payment_success_message', 'payment_error_message'])->delete();
        Setting::create([
            'group' => 'checkout', 'key' => 'payment_success_message',
            'value' => json_encode(array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Payment received. Thank you!')),
            'type' => 'json',
        ]);
        Setting::create([
            'group' => 'checkout', 'key' => 'payment_error_message',
            'value' => json_encode(array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Payment failed. Please try again.')),
            'type' => 'json',
        ]);

        $migration = require database_path('migrations/2026_08_16_000002_reapply_checkout_message_translations.php');
        $migration->up();

        $success = json_decode(Setting::where('group', 'checkout')->where('key', 'payment_success_message')->value('value'), true);
        $error = json_decode(Setting::where('group', 'checkout')->where('key', 'payment_error_message')->value('value'), true);

        $this->assertSame('Zahlung erhalten. Vielen Dank!', $success['de']);
        $this->assertSame('Le paiement a échoué. Veuillez réessayer.', $error['fr']);
        $this->assertNotSame($success['en'], $success['de'], 'locales must no longer be identical after the fix');
    }

    #[Test]
    public function checkout_and_payments_tab_renders_and_saves_per_locale_customer_messages(): void
    {
        Livewire::test(StoreOperationsSettings::class)
            ->assertSet('data.payment_success_message.en', 'Payment received. Thank you!')
            ->assertSet('data.payment_success_message.de', 'Zahlung erhalten. Vielen Dank!')
            ->set('data.payment_error_message.de', 'Regressionstest-Nachricht')
            ->call('save')
            ->assertHasNoErrors();

        Cache::forget('settings.checkout');
        $saved = json_decode(settings('checkout.payment_error_message'), true);
        $this->assertSame('Regressionstest-Nachricht', $saved['de']);
    }

    protected function tearDown(): void
    {
        Cache::forget('settings.general');
        Cache::forget('settings.store');
        Cache::forget('settings.checkout');

        parent::tearDown();
    }
}
