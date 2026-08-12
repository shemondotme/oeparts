<?php

namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedE2eGuestFixtureCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('framework/testing/e2e-guest-fixture-prior-detail-pages-enabled.txt'));

        parent::tearDown();
    }

    #[Test]
    public function seeding_creates_a_product_with_images_cross_references_and_enables_detail_pages(): void
    {
        $this->artisan('oeparts:e2e:seed-guest-fixture')->assertSuccessful();

        $product = Product::where('oem_number', 'E2EGUEST001')->first();

        $this->assertNotNull($product);
        $this->assertCount(2, $product->images);
        $this->assertCount(2, $product->crossReferences);
        $this->assertTrue(filter_var(settings('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN));

        $this->assertDatabaseHas('manufacturers', ['slug' => 'e2e-guest-fixture']);
    }

    #[Test]
    public function seeding_twice_is_idempotent(): void
    {
        $this->artisan('oeparts:e2e:seed-guest-fixture')->assertSuccessful();
        $this->artisan('oeparts:e2e:seed-guest-fixture')->assertSuccessful();

        $this->assertSame(1, Product::where('oem_number', 'E2EGUEST001')->count());
        $this->assertSame(1, Manufacturer::where('slug', 'e2e-guest-fixture')->count());
    }

    #[Test]
    public function cleanup_removes_the_fixture_and_restores_the_prior_toggle_state(): void
    {
        app(SettingsService::class)->set('seo.detail_pages_enabled', 'false');

        $this->artisan('oeparts:e2e:seed-guest-fixture')->assertSuccessful();
        $this->assertTrue(filter_var(settings('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN));

        $this->artisan('oeparts:e2e:seed-guest-fixture', ['--cleanup' => true])->assertSuccessful();

        $this->assertDatabaseMissing('products', ['oem_number' => 'E2EGUEST001']);
        $this->assertDatabaseMissing('manufacturers', ['slug' => 'e2e-guest-fixture']);
        $this->assertFalse(filter_var(settings('seo.detail_pages_enabled', true), FILTER_VALIDATE_BOOLEAN));
    }

    #[Test]
    public function it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('oeparts:e2e:seed-guest-fixture')->assertFailed();

        $this->assertDatabaseMissing('products', ['oem_number' => 'E2EGUEST001']);
    }
}
