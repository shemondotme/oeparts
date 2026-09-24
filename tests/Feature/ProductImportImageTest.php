<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImportService;
use App\Services\RemoteImageDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulk product import's image_urls column — the CSV-import counterpart to
 * ProductResource's single-product "fetch from an image URL" field. Reuses
 * the same RemoteImageDownloadService, so DNS resolution is stubbed exactly
 * like RemoteImageDownloadServiceTest does (a partial mock over resolveHost)
 * rather than hitting real DNS/network from a test.
 */
class ProductImportImageTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal but genuine 1x1 PNG. */
    private const TINY_PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $downloader = \Mockery::mock(RemoteImageDownloadService::class)->makePartial();
        $downloader->shouldAllowMockingProtectedMethods();
        $downloader->shouldReceive('resolveHost')->andReturn('93.184.216.34');
        $this->app->instance(RemoteImageDownloadService::class, $downloader);
    }

    private function seedCatalog(): void
    {
        Manufacturer::factory()->create(['slug' => 'bmw']);
        Condition::create([
            'name' => 'New', 'slug' => 'new',
            'bg_color' => '#DCFCE7', 'text_color' => '#166534',
            'is_active' => true, 'sort_order' => 0,
        ]);
    }

    private function importCsv(array $rows): void
    {
        $admin = Admin::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        app(ProductImportService::class)->process($path, $admin->id, updateExisting: true);

        unlink($path);
    }

    #[Test]
    public function it_downloads_and_attaches_images_from_pipe_separated_urls(): void
    {
        $this->seedCatalog();
        Http::fake(['*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);

        $this->importCsv([
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock', 'image_urls'],
            ['OEM1', 'bmw', 'new', '10.00', '1', 'https://scraped-site.test/1.png|https://scraped-site.test/2.png'],
        ]);

        $product = Product::where('oem_number', 'OEM1')->firstOrFail();
        $this->assertSame(2, $product->images()->count());

        $first = $product->images()->orderBy('sort_order')->first();
        $second = $product->images()->orderBy('sort_order')->skip(1)->first();

        $this->assertTrue($first->is_featured, 'the first successfully fetched image becomes the featured one');
        $this->assertFalse($second->is_featured);
        $this->assertSame('https://scraped-site.test/1.png', $first->source_url);
        $this->assertSame('https://scraped-site.test/2.png', $second->source_url);
        Storage::disk('public')->assertExists($first->path);
        Storage::disk('public')->assertExists($second->path);
    }

    #[Test]
    public function it_does_not_duplicate_images_when_the_same_csv_is_re_imported(): void
    {
        $this->seedCatalog();
        Http::fake(['*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);

        $rows = [
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock', 'image_urls'],
            ['OEM1', 'bmw', 'new', '10.00', '1', 'https://scraped-site.test/1.png'],
        ];

        $this->importCsv($rows);
        $this->importCsv($rows);

        $product = Product::where('oem_number', 'OEM1')->firstOrFail();
        $this->assertSame(1, $product->images()->count(), 're-importing the same URL must not create a duplicate image');
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_adds_a_newly_listed_url_without_touching_previously_imported_images(): void
    {
        $this->seedCatalog();
        Http::fake(['*' => Http::response(self::TINY_PNG, 200, ['Content-Type' => 'image/png'])]);

        $this->importCsv([
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock', 'image_urls'],
            ['OEM1', 'bmw', 'new', '10.00', '1', 'https://scraped-site.test/1.png'],
        ]);

        $this->importCsv([
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock', 'image_urls'],
            ['OEM1', 'bmw', 'new', '10.00', '1', 'https://scraped-site.test/1.png|https://scraped-site.test/2.png'],
        ]);

        $product = Product::where('oem_number', 'OEM1')->firstOrFail();
        $this->assertSame(2, $product->images()->count());
        $this->assertSame(1, $product->images()->where('is_featured', true)->count(), 'the already-featured image must not be reassigned');
        $this->assertTrue($product->images()->where('source_url', 'https://scraped-site.test/1.png')->first()->is_featured);
    }

    #[Test]
    public function a_broken_image_url_does_not_fail_the_rest_of_the_row(): void
    {
        $this->seedCatalog();
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $this->importCsv([
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock', 'image_urls'],
            ['OEM1', 'bmw', 'new', '10.00', '1', 'https://scraped-site.test/missing.png'],
        ]);

        $product = Product::where('oem_number', 'OEM1')->first();
        $this->assertNotNull($product, 'a bad image URL must not prevent the product itself from being imported');
        $this->assertSame(0, $product->images()->count());
    }

    #[Test]
    public function a_row_without_the_image_urls_column_is_unaffected(): void
    {
        $this->seedCatalog();

        $this->importCsv([
            ['oem_number', 'manufacturer_slug', 'condition_slug', 'price', 'is_in_stock'],
            ['OEM1', 'bmw', 'new', '10.00', '1'],
        ]);

        $product = Product::where('oem_number', 'OEM1')->firstOrFail();
        $this->assertSame(0, ProductImage::where('product_id', $product->id)->count());
    }
}
