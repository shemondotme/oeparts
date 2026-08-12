<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\CrawlerVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchRateLimitCrawlerBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A tight limit makes the test fast and deterministic — the point
        // is whether the bypass applies, not the real production number.
        Setting::updateOrCreate(
            ['group' => 'search', 'key' => 'rate_limit_per_minute'],
            ['value' => '2', 'type' => 'string', 'is_encrypted' => false]
        );

        // Zero-result searches now return a real HTTP 404 (§3.3 of the SEO
        // program) — seed a real match so this test's 200s are testing the
        // rate-limit bypass itself, not incidentally asserting on the
        // zero-results status code.
        Product::factory()->create(['oem_number' => '06L906036L', 'normalized_oem' => '06L906036L']);
    }

    #[Test]
    public function verified_crawler_ip_is_never_rate_limited(): void
    {
        $this->app->bind(CrawlerVerificationService::class, function () {
            return new class extends CrawlerVerificationService {
                public function isVerifiedCrawler(string $ip): bool
                {
                    return true;
                }
            };
        });

        // Exceed the configured limit (2/min) by a wide margin — a verified
        // crawler must never see a 429, no matter how many requests it makes.
        for ($i = 0; $i < 5; $i++) {
            $response = $this->get('/en/parts/06L906036L');
            $response->assertStatus(200);
        }
    }

    #[Test]
    public function non_verified_visitor_is_still_rate_limited(): void
    {
        $this->app->bind(CrawlerVerificationService::class, function () {
            return new class extends CrawlerVerificationService {
                public function isVerifiedCrawler(string $ip): bool
                {
                    return false;
                }
            };
        });

        $this->get('/en/parts/06L906036L')->assertStatus(200);
        $this->get('/en/parts/06L906036L')->assertStatus(200);

        // Third request exceeds the 2/min limit — existing behavior for a
        // normal (non-crawler) visitor must be unchanged.
        $response = $this->get('/en/parts/06L906036L');
        $response->assertStatus(429);
        $response->assertHeader('Retry-After', '60');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('search:127.0.0.1');
        parent::tearDown();
    }
}
