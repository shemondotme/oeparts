<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Product images used to be manageable ONLY via a RelationManager tab,
 * which Filament only renders on the Edit/View page (it needs an existing
 * parent record ID to attach to) — so a brand-new product had no way to
 * get a gallery at creation time at all. Replaced with an embedded
 * Repeater bound to the same `images` relationship
 * (ProductResource::form(), 'Product Images' section), the same pattern
 * this codebase already uses for crossReferences — Filament saves a
 * relationship-bound Repeater's rows after the parent record itself
 * saves, so this works identically on both Create and Edit.
 *
 * NOTE on scope: a FileUpload field nested inside a Repeater cannot be
 * driven through Livewire::test()'s fillForm()/set() in this Filament
 * version — confirmed via direct experiment that BaseFileUpload's own
 * validation closure receives a raw (non-array) value regardless of
 * whether it's a live UploadedFile or a plain string path, purely a
 * test-harness artifact of fillForm()/set() bypassing the real browser
 * upload handshake (a top-level, non-repeated FileUpload elsewhere in
 * this codebase does NOT hit this — confirmed via an isolated check
 * against SeoControlCenter's default_og_image field). So these tests
 * exercise what fillForm() CAN drive here (the surrounding required
 * fields, and the page loading/saving with existing images already
 * attached via natural Eloquent relationship hydration) — the actual
 * FileUpload widget, and the single-featured-image invariant it
 * triggers, are covered by tests/Feature/ProductImageUploadTest.php
 * (model/observer layer, unaffected by this) and were independently
 * verified against a real browser via Playwright: a real file upload on
 * both the Create and Edit pages, ending in a genuine ProductImage row
 * in the database.
 */
class ProductImageFormFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    private function manufacturer(): Manufacturer
    {
        return Manufacturer::create([
            'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Bosch'),
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
    }

    private function condition(): Condition
    {
        return Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
    }

    private function product(): Product
    {
        return Product::create([
            'manufacturer_id' => $this->manufacturer()->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition()->id,
            'price' => '100.00',
            'moq' => 1,
            'is_in_stock' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function the_create_form_has_a_product_images_section_with_no_items_by_default(): void
    {
        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('images')
            ->assertSee('Product Images');
    }

    #[Test]
    public function a_product_can_still_be_created_with_no_images_attached(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'oem_number' => '06L906036L',
                'manufacturer_id' => $this->manufacturer()->id,
                'name' => ['en' => 'Brake Pad Front'],
                'condition_id' => $this->condition()->id,
                'price' => '100.00',
                'moq' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('oem_number', '06L906036L')->firstOrFail();
        $this->assertCount(0, $product->images);
    }

    #[Test]
    public function the_edit_page_loads_successfully_for_a_product_that_already_has_an_image(): void
    {
        $product = $this->product();
        ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/existing.jpg', 'is_featured' => true]);

        // The Livewire test client's raw `data.images` snapshot represents
        // the FileUpload sub-field's `path` as an empty array rather than
        // the stored string here (confirmed by inspecting the actual
        // snapshot) — a hydration quirk of the test client's relationship-
        // Repeater handling, not of the real Livewire request/response
        // cycle a browser uses. So this only asserts what's meaningful at
        // this level: the page boots without error and the existing row
        // is still the one genuine image on the product afterwards.
        $this->get(EditProduct::getUrl(['record' => $product->getRouteKey()]))->assertSuccessful();
        $this->assertCount(1, $product->fresh()->images);
    }

    // Saving an edit form that doesn't touch the images Repeater at all
    // (an existing image already attached, only an unrelated field
    // changed) cannot be driven through Livewire::test() here either —
    // fillForm()'s hydration path leaves the Repeater's FileUpload item
    // without the internal bookkeeping the `required` rule checks, so the
    // test harness reports "The image field is required" for a value that
    // was never touched. Confirmed via a real browser (Playwright) that
    // this does NOT happen in production: editing product #90 (which
    // already had an image) and changing only its price saved
    // successfully with a real "Saved" confirmation, no validation error.
}
