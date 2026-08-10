<?php

namespace Tests\Unit;

use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * generateCarModelsSitemap() only checked the car model's own is_active,
 * not its manufacturer's — CarModelController::show() requires BOTH active
 * or 404s, so a car model belonging to a deactivated manufacturer used to
 * keep its URL in the sitemap pointing at a page that immediately 404s on
 * crawl.
 */
class SitemapCarModelsTest extends TestCase
{
    use RefreshDatabase;

    private string $outputFile;

    protected function tearDown(): void
    {
        if (isset($this->outputFile) && file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }

        parent::tearDown();
    }

    private function generateCarModelsSitemap(): string
    {
        $service = app(SitemapService::class);
        $method = new \ReflectionMethod($service, 'generateCarModelsSitemap');
        $method->setAccessible(true);
        $method->invoke($service);

        $this->outputFile = public_path('sitemaps/sitemap-models.xml');

        return file_exists($this->outputFile) ? file_get_contents($this->outputFile) : '';
    }

    #[Test]
    public function car_models_of_an_inactive_manufacturer_are_excluded(): void
    {
        $activeManufacturer = Manufacturer::create([
            'name' => ['en' => 'Active Mfr'], 'slug' => 'active-mfr',
            'country_code' => 'DE', 'is_active' => true,
        ]);
        $inactiveManufacturer = Manufacturer::create([
            'name' => ['en' => 'Inactive Mfr'], 'slug' => 'inactive-mfr',
            'country_code' => 'DE', 'is_active' => false,
        ]);

        CarModel::create(['manufacturer_id' => $activeManufacturer->id, 'name' => 'Active Model', 'slug' => 'active-model', 'is_active' => true]);
        CarModel::create(['manufacturer_id' => $inactiveManufacturer->id, 'name' => 'Orphaned Model', 'slug' => 'orphaned-model', 'is_active' => true]);

        $xml = $this->generateCarModelsSitemap();

        $this->assertStringContainsString('active-mfr/active-model', $xml);
        $this->assertStringNotContainsString('inactive-mfr/orphaned-model', $xml);
    }
}
