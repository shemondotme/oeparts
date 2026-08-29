<?php

namespace App\Console\Commands;

use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Seeds (or tears down) a small, deterministic storefront fixture — two
 * in-stock products, a manufacturer, a car model, and a coupon — for the
 * broader Playwright guest e2e suite (cart, checkout, brand/car-model
 * pages, coupon application).
 *
 * Kept as its own command rather than folded into SeedE2eGuestFixture
 * (narrowly scoped to the search -> detail -> gallery -> cross-reference
 * suite) since the two seed genuinely different data — but both need
 * seo.detail_pages_enabled on: confirmed live that with it off (this
 * dev DB's default), a single-OEM match stays on the hub/results
 * template, which has no add-to-cart/review/inquiry UI at all — only
 * the dedicated detail template does. Uses its own marker file (not
 * SeedE2eGuestFixture's) so the two commands' seed/cleanup lifecycles
 * never clobber each other if a run interleaves them.
 *
 * Two products, not one, so cart/checkout tests can exercise a multi-item
 * cart; their combined price (79.99 + 39.99 = 119.98) clears the fixture
 * coupon's own min_order_amount comfortably even after either product
 * alone is removed from the cart mid-test.
 */
class SeedE2eStorefrontFixture extends Command
{
    protected $signature = 'oeparts:e2e:seed-storefront-fixture {--cleanup}';

    protected $description = 'Seed or tear down the deterministic product/coupon fixture used by the broader Playwright guest e2e suite';

    private const MANUFACTURER_SLUG = 'e2e-storefront-fixture';

    private const CAR_MODEL_SLUG = 'e2e-storefront-fixture-model';

    public const OEM_A = 'E2ESTOREA1';

    public const OEM_B = 'E2ESTOREB2';

    public const COUPON_CODE = 'E2ESTOREWELCOME';

    public function handle(SettingsService $settings): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production — this seeds/deletes public storefront content.');

            return self::FAILURE;
        }

        return $this->option('cleanup') ? $this->cleanup($settings) : $this->seed($settings);
    }

    private function markerPath(): string
    {
        return storage_path('framework/testing/e2e-storefront-fixture-prior-detail-pages-enabled.txt');
    }

    private function seed(SettingsService $settings): int
    {
        File::ensureDirectoryExists(dirname($this->markerPath()));
        if (! File::exists($this->markerPath())) {
            File::put($this->markerPath(), (string) settings('seo.detail_pages_enabled', '0'));
        }
        $settings->set('seo.detail_pages_enabled', 'true');

        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );

        $manufacturer = Manufacturer::firstOrCreate(
            ['slug' => self::MANUFACTURER_SLUG],
            [
                'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'E2E Storefront Fixture Motors'),
                'country_code' => 'DE',
                'is_active' => true,
            ]
        );

        CarModel::firstOrCreate(
            ['slug' => self::CAR_MODEL_SLUG],
            [
                'manufacturer_id' => $manufacturer->id,
                'name' => 'E2E Fixture Model X',
                'year_from' => 2018,
                'year_to' => 2024,
                'is_active' => true,
            ]
        );

        // updateOrCreate, not firstOrCreate: a completed checkout test run
        // for real flips is_in_stock false on whatever it just "bought"
        // (confirmed live — a prior full checkout run against this exact
        // fixture left OEM_A permanently out of stock, silently 422'ing
        // every add-to-cart afterward since firstOrCreate is a no-op once
        // the row exists). Every seed call must reset stock/price/status
        // back to known-good, not just create the row once.
        $productA = Product::withTrashed()->updateOrCreate(
            ['oem_number' => self::OEM_A],
            [
                'manufacturer_id' => $manufacturer->id,
                'normalized_oem' => self::OEM_A,
                'condition_id' => $condition->id,
                'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'E2E Storefront Fixture Filter'),
                'description' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Seeded fixture product for the Playwright storefront e2e suite — safe to ignore.'),
                'price' => '79.99',
                'delivery_time' => '2-4 days',
                'moq' => 1,
                'is_in_stock' => true,
                'is_active' => true,
                'deleted_at' => null,
            ]
        );

        $productB = Product::withTrashed()->updateOrCreate(
            ['oem_number' => self::OEM_B],
            [
                'manufacturer_id' => $manufacturer->id,
                'normalized_oem' => self::OEM_B,
                'condition_id' => $condition->id,
                'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'E2E Storefront Fixture Spark Plug'),
                'description' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Seeded fixture product for the Playwright storefront e2e suite — safe to ignore.'),
                'price' => '39.99',
                'delivery_time' => '2-4 days',
                'moq' => 1,
                'is_in_stock' => true,
                'is_active' => true,
                'deleted_at' => null,
            ]
        );

        foreach ([$productA, $productB] as $product) {
            if ($product->images()->count() === 0) {
                ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/e2e-storefront-fixture.jpg', 'is_featured' => true, 'sort_order' => 0]);
            }
        }

        Coupon::firstOrCreate(
            ['code' => self::COUPON_CODE],
            [
                'name' => 'E2E Storefront Fixture Coupon',
                'discount_type' => 'percentage',
                'discount_value' => '10.00',
                'min_order_amount' => '10.00',
                'usage_limit' => null,
                'usage_limit_per_user' => null,
                'expires_at' => now()->addYears(5),
                'is_active' => true,
                'created_by' => \App\Models\Admin::query()->value('id'),
            ]
        );

        $this->info("Storefront e2e fixture ready: {$productA->normalized_oem} (#{$productA->id}), {$productB->normalized_oem} (#{$productB->id}), coupon ".self::COUPON_CODE.'.');

        return self::SUCCESS;
    }

    private function cleanup(SettingsService $settings): int
    {
        foreach ([self::OEM_A, self::OEM_B] as $oem) {
            Product::withTrashed()->where('oem_number', $oem)->first()?->forceDelete();
        }

        Coupon::where('code', self::COUPON_CODE)->delete();
        CarModel::where('slug', self::CAR_MODEL_SLUG)->delete();
        Manufacturer::where('slug', self::MANUFACTURER_SLUG)->delete();

        if (File::exists($this->markerPath())) {
            $settings->set('seo.detail_pages_enabled', File::get($this->markerPath()));
            File::delete($this->markerPath());
        }

        $this->info('Storefront e2e fixture removed.');

        return self::SUCCESS;
    }
}
