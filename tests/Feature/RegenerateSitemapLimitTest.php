<?php

namespace Tests\Feature;

use App\Jobs\RegenerateSitemap;
use App\Models\Product;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A queued sitemap build is cut off at the job's one-hour limit and would leave a half-written sitemap on a big
 * catalogue (1.76M parts took 62 minutes). Above the configured size the job declines and leaves the files alone.
 */
class RegenerateSitemapLimitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_catalogue_above_the_limit_is_left_to_the_nightly_run(): void
    {
        config(['queue.sitemap_product_limit' => 1]);
        Product::factory()->count(2)->create(['is_active' => true]);

        $this->mock(SitemapService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('generateAll');
        });

        (new RegenerateSitemap('Test'))->handle(app(SitemapService::class));

        $this->addToAssertionCount(1); // generateAll() was not called (Mockery verifies it on tear-down)
    }

    #[Test]
    public function a_catalogue_within_the_limit_is_built_as_before(): void
    {
        config(['queue.sitemap_product_limit' => 10]);
        Product::factory()->count(2)->create(['is_active' => true]);

        $this->mock(SitemapService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateAll')->once()->andReturn(['sitemap-parts-1.xml']);
        });

        (new RegenerateSitemap('Test'))->handle(app(SitemapService::class));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_limit_of_zero_turns_the_check_off(): void
    {
        config(['queue.sitemap_product_limit' => 0]);
        Product::factory()->count(2)->create(['is_active' => true]);

        $this->mock(SitemapService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generateAll')->once()->andReturn([]);
        });

        (new RegenerateSitemap('Test'))->handle(app(SitemapService::class));

        $this->addToAssertionCount(1);
    }
}
