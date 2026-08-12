<?php

namespace Tests\Feature;

use App\Jobs\ProcessProductImage;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch', 'de' => 'Bosch', 'lt' => 'Bosch', 'fr' => 'Bosch', 'es' => 'Bosch'],
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
        ]);

        return Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'name' => ['en' => 'Brake Pad Front'],
            'condition_id' => $condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function processing_job_is_dispatched_when_a_product_image_is_created(): void
    {
        Bus::fake();
        $product = $this->makeProduct();

        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/test.jpg',
            'is_featured' => true,
        ]);

        Bus::assertDispatched(ProcessProductImage::class, fn ($job) => $job->productImageId === $image->id);
    }

    #[Test]
    public function thumbnail_url_falls_back_to_the_original_path_when_no_derived_file_exists(): void
    {
        // Covers the GD-unavailable / job-hasn't-run-yet branch without
        // needing GD installed or a queue worker running in CI.
        $product = $this->makeProduct();
        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/original.jpg',
            'is_featured' => true,
        ]);

        $this->assertStringContainsString('original.jpg', $image->thumbnail_url);
        $this->assertStringContainsString('original.jpg', $image->medium_url);
    }

    #[Test]
    public function setting_a_new_image_as_featured_unsets_the_previous_featured_image(): void
    {
        $product = $this->makeProduct();
        $first = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/first.jpg',
            'is_featured' => true,
        ]);

        $second = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/second.jpg',
            'is_featured' => true,
        ]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    #[Test]
    public function toggling_an_existing_image_to_featured_unsets_the_others(): void
    {
        $product = $this->makeProduct();
        $first = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/first.jpg',
            'is_featured' => true,
        ]);
        $second = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/second.jpg',
            'is_featured' => false,
        ]);

        $second->update(['is_featured' => true]);

        $this->assertFalse($first->fresh()->is_featured);
        $this->assertTrue($second->fresh()->is_featured);
    }

    #[Test]
    public function processing_job_no_ops_cleanly_when_the_image_row_is_gone(): void
    {
        // Row deleted before the job ran (e.g. admin deleted it right
        // after upload) — must not throw.
        $job = new ProcessProductImage(999999);

        $job->handle();

        $this->addToAssertionCount(1); // reaching here without an exception is the assertion
    }

    #[Test]
    public function processing_job_generates_real_thumbnail_and_medium_files_when_gd_is_available(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available in this environment.');
        }

        Storage::fake('public');
        $product = $this->makeProduct();

        // A real 300x200 JPEG — large enough that cover(150,150) actually
        // crops and scaleDown(width:800) is a no-op (already under 800px),
        // exercising both derived-image code paths for real.
        $canvas = imagecreatetruecolor(300, 200);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 120, 140, 160));
        $tmpPath = tempnam(sys_get_temp_dir(), 'oeparts_test_img_') . '.jpg';
        imagejpeg($canvas, $tmpPath);
        imagedestroy($canvas);

        Storage::disk('public')->put('product-images/real-test.jpg', file_get_contents($tmpPath));
        unlink($tmpPath);

        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'product-images/real-test.jpg',
            'is_featured' => true,
        ]);

        (new ProcessProductImage($image->id))->handle();
        $image->refresh();

        $this->assertNotNull($image->thumbnail_path);
        $this->assertNotNull($image->medium_path);
        Storage::disk('public')->assertExists($image->thumbnail_path);
        Storage::disk('public')->assertExists($image->medium_path);

        [$thumbWidth, $thumbHeight] = getimagesize(Storage::disk('public')->path($image->thumbnail_path));
        $this->assertSame(150, $thumbWidth);
        $this->assertSame(150, $thumbHeight);
    }
}
