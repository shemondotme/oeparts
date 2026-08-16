<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\ManufacturerResource\Pages\CreateManufacturer;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
