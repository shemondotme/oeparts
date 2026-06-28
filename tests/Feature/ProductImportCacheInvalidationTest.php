<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    // Regression test for Phase 7 performance audit (Option NN):
    // CacheService::forgetHeroStats()/forgetPopularOems() exist specifically
    // to be called "after bulk product/manufacturer imports" (per their own
    // docblocks) but were never actually called anywhere — the homepage hero
    // stats and popular-OEMs strip stayed stale for up to 6h/1h after a
    // bulk CSV import. ProductImportService::process() already forgets two
    // other cache keys unconditionally after the row loop, regardless of
    // how many rows succeed — so an empty/header-only CSV is enough to
    // exercise the fix.

    #[Test]
    public function bulk_import_invalidates_hero_stats_and_popular_oems_cache(): void
    {
        Cache::put('hero.stats', 'stale-value', now()->addHours(6));
        Cache::put('hero.popular_oems', 'stale-value', now()->addHour());

        $admin = Admin::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "oem_number,manufacturer_slug,condition_slug,price,is_in_stock\n");

        app(ProductImportService::class)->process($path, $admin->id, updateExisting: false);

        unlink($path);

        $this->assertNull(Cache::get('hero.stats'));
        $this->assertNull(Cache::get('hero.popular_oems'));
    }
}
