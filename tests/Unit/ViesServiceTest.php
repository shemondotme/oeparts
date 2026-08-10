<?php

namespace Tests\Unit;

use App\Services\ViesResult;
use App\Services\ViesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * system-14: RateLimiter::hit() used to run unconditionally BEFORE the
 * 24-hour Cache::remember() check — the exact same VAT number re-validated
 * repeatedly, served entirely from cache with no real VIES call made,
 * still burned through the 30-request budget every single time.
 */
class ViesServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_cache_served_lookup_does_not_consume_rate_limit_budget(): void
    {
        $cacheKey = 'vies:DE:123456789';
        $rateKey = 'vies:127.0.0.1';

        Cache::put($cacheKey, new ViesResult(valid: true, reason: null, countryCode: 'DE', vatNumber: '123456789'), 86400);
        RateLimiter::clear($rateKey);

        $result = app(ViesService::class)->validate('DE', '123456789');

        $this->assertTrue($result->valid);
        $this->assertSame(0, RateLimiter::attempts($rateKey), 'a cache hit must not count against the rate limit');
    }
}
