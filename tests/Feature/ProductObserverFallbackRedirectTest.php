<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Redirect;
use App\Services\ProductSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A discontinued/deactivated product's own detail URL used to just 404 with
 * no fallback until a real visitor or crawler hit it, got logged, and an
 * admin eventually noticed and manually created a redirect. ProductObserver
 * now proactively creates one the moment the product goes away.
 */
class ProductObserverFallbackRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;

    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);
    }

    private function detailPath(Product $product, string $locale = 'en'): string
    {
        $idSlug = app(ProductSlugService::class)->buildIdSlug($product, $locale);

        return "{$locale}/parts/{$product->normalized_oem}/{$idSlug}";
    }

    #[Test]
    public function deleting_a_product_creates_a_redirect_from_its_detail_url_to_the_manufacturer_page(): void
    {
        $product = $this->makeProduct();
        $detailPath = strtolower($this->detailPath($product));

        $product->delete();

        $this->assertDatabaseHas('redirects', [
            'from_url' => $detailPath,
            'to_url' => route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]),
        ]);
    }

    #[Test]
    public function the_created_redirect_actually_fires_for_a_real_request(): void
    {
        $product = $this->makeProduct();
        $detailPath = $this->detailPath($product);

        $product->delete();

        $response = $this->get('/'.$detailPath);

        $response->assertRedirect(route('frontend.manufacturer.show', ['lang' => 'en', 'manufacturer' => $this->manufacturer->slug]));
        $response->assertStatus(301);
    }

    #[Test]
    public function deactivating_a_product_without_deleting_it_also_creates_the_fallback_redirect(): void
    {
        $product = $this->makeProduct();

        $product->update(['is_active' => false]);

        $this->assertDatabaseHas('redirects', [
            'from_url' => strtolower($this->detailPath($product)),
        ]);
    }

    #[Test]
    public function reactivating_a_product_removes_the_fallback_redirect(): void
    {
        $product = $this->makeProduct();
        $product->update(['is_active' => false]);
        $this->assertDatabaseHas('redirects', ['from_url' => strtolower($this->detailPath($product))]);

        $product->update(['is_active' => true]);

        $this->assertDatabaseMissing('redirects', ['from_url' => strtolower($this->detailPath($product))]);
    }

    #[Test]
    public function deactivating_twice_in_a_row_does_not_create_a_duplicate_redirect(): void
    {
        $product = $this->makeProduct();

        $product->update(['is_active' => false]);
        // A second, unrelated save while still inactive (e.g. editing the
        // price) must not attempt a second insert against the unique
        // from_url column.
        $product->update(['price' => '105.00']);

        $this->assertSame(
            1,
            Redirect::where('from_url', strtolower($this->detailPath($product)))->count()
        );
    }

    #[Test]
    public function unrelated_product_updates_do_not_touch_redirects(): void
    {
        $product = $this->makeProduct();

        $product->update(['price' => '120.00']);

        $this->assertDatabaseMissing('redirects', ['from_url' => strtolower($this->detailPath($product))]);
    }
}
