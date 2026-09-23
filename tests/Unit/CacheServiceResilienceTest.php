<?php

namespace Tests\Unit;

use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 18 (Infrastructure/Ops Resilience). Production runs CACHE_STORE=redis
 * — a Redis outage previously took down almost the entire storefront (every
 * page touching a section, manufacturer list, condition list, blog listing,
 * homepage stat, or the checkout coupon lookup) with a hard 500, because
 * only rememberSection() among this class's ~11 rememberX() methods caught
 * Cache::remember()'s exception; every other one called it unguarded. All of
 * them now funnel through safeRemember(), which falls back to computing the
 * value live (slower, but the site stays up) and logs instead of throwing.
 * Simulates the outage via Cache::shouldReceive('remember')->andThrow(),
 * the standard way to force a facade call to fail without touching a real
 * Redis connection.
 */
class CacheServiceResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CacheService
    {
        return app(CacheService::class);
    }

    #[Test]
    public function a_coupon_lookup_still_returns_the_live_value_when_the_cache_store_is_down(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Connection refused [tcp://redis:6379]'));
        Log::spy();

        $result = $this->service()->rememberCouponByCode('SAVE10', fn () => 'coupon-computed-live');

        $this->assertSame('coupon-computed-live', $result);
        Log::shouldHaveReceived('error')->once()
            ->withArgs(fn ($message) => str_contains($message, 'rememberCouponByCode'));
    }

    #[Test]
    public function homepage_sections_still_render_live_content_when_the_cache_store_is_down(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Connection refused'));
        Log::spy();

        $result = $this->service()->rememberSection('homepage', fn () => ['section' => 'computed-live']);

        $this->assertSame(['section' => 'computed-live'], $result);
    }

    #[Test]
    public function the_manufacturer_list_still_returns_live_data_when_the_cache_store_is_down(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Connection refused'));

        $result = $this->service()->rememberManufacturers(fn () => collect(['live-manufacturer']));

        $this->assertSame(['live-manufacturer'], $result->all());
    }

    /**
     * A missing Redis extension surfaces as \Error ("Class \"Redis\" not
     * found"), not \Exception — the exact gap SettingsService::getGroup()
     * was already fixed for (Phase 17 review). rememberSection()'s own
     * catch used to be catch(\Exception), which would NOT have caught this.
     */
    #[Test]
    public function a_php_error_not_just_an_exception_still_falls_back_to_the_live_value(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \Error('Class "Redis" not found'));

        $result = $this->service()->rememberHeroStats(fn () => ['stats' => 'computed-live']);

        $this->assertSame(['stats' => 'computed-live'], $result);
    }

    #[Test]
    public function the_generic_remember_helper_also_degrades_gracefully(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Connection refused'));

        $result = $this->service()->remember('arbitrary.key', 10, fn () => 'live-value');

        $this->assertSame('live-value', $result);
    }
}
