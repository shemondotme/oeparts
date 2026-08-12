<?php

namespace App\Console\Commands;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\ProductImage;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Seeds (or tears down) one deterministic, uniquely-named product — with a
 * multi-image gallery and cross-references — for the Playwright guest e2e
 * suite (tests/e2e/guest/). The demo catalog seeder (DemoManufacturersAndPartsSeeder)
 * doesn't create images or cross-references, so the guest gallery-swap and
 * cross-reference-navigation scenarios need their own dedicated fixture
 * rather than relying on whatever happens to already be in the dev DB.
 *
 * Deliberately narrow and idempotent: re-running --seed on an
 * already-seeded fixture just returns the existing rows unchanged (no
 * duplicate manufacturers/products), and --cleanup removes exactly what
 * this command created, restoring seo.detail_pages_enabled to whatever it
 * was before --seed ran (tracked in a local marker file, since two
 * separate CLI invocations don't share PHP process memory).
 */
class SeedE2eGuestFixture extends Command
{
    protected $signature = 'oeparts:e2e:seed-guest-fixture {--cleanup}';

    protected $description = 'Seed or tear down the deterministic product fixture used by the Playwright guest e2e suite';

    private const MANUFACTURER_SLUG = 'e2e-guest-fixture';

    private const OEM_NUMBER = 'E2EGUEST001';

    private const CROSS_OEM_1 = 'E2EGUESTX1';

    private const CROSS_OEM_2 = 'E2EGUESTX2';

    public function handle(SettingsService $settings): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production — this seeds/deletes public storefront content.');

            return self::FAILURE;
        }

        return $this->option('cleanup')
            ? $this->cleanup($settings)
            : $this->seed($settings);
    }

    private function markerPath(): string
    {
        return storage_path('framework/testing/e2e-guest-fixture-prior-detail-pages-enabled.txt');
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
                'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'E2E Guest Fixture Co'),
                'country_code' => 'DE',
                'is_active' => true,
            ]
        );

        $product = Product::withTrashed()->firstOrCreate(
            ['oem_number' => self::OEM_NUMBER],
            [
                'manufacturer_id' => $manufacturer->id,
                'normalized_oem' => self::OEM_NUMBER,
                'condition_id' => $condition->id,
                'name' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'E2E Guest Fixture Brake Pad'),
                'description' => array_fill_keys(['en', 'de', 'lt', 'fr', 'es'], 'Seeded fixture product for the Playwright guest e2e suite — safe to ignore.'),
                'price' => '99.99',
                'delivery_time' => '2-4 days',
                'moq' => 1,
                'is_in_stock' => true,
                'is_active' => true,
            ]
        );

        if ($product->images()->count() === 0) {
            ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/e2e-fixture-featured.jpg', 'is_featured' => true, 'sort_order' => 0]);
            ProductImage::create(['product_id' => $product->id, 'path' => 'product-images/e2e-fixture-gallery-1.jpg', 'is_featured' => false, 'sort_order' => 1]);
        }

        foreach ([self::CROSS_OEM_1, self::CROSS_OEM_2] as $crossOem) {
            ProductCrossReference::firstOrCreate(
                ['product_id' => $product->id, 'normalized_cross_oem' => $crossOem],
                ['cross_oem_number' => $crossOem]
            );
        }

        $this->info("Guest e2e fixture ready: OEM {$product->oem_number} (product #{$product->id}).");

        return self::SUCCESS;
    }

    private function cleanup(SettingsService $settings): int
    {
        $product = Product::withTrashed()->where('oem_number', self::OEM_NUMBER)->first();
        $product?->forceDelete(); // cascades product_images/product_cross_references

        Manufacturer::where('slug', self::MANUFACTURER_SLUG)->delete();

        if (File::exists($this->markerPath())) {
            $settings->set('seo.detail_pages_enabled', File::get($this->markerPath()));
            File::delete($this->markerPath());
        }

        $this->info('Guest e2e fixture removed.');

        return self::SUCCESS;
    }
}
