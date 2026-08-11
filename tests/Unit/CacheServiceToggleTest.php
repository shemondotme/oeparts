<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\CacheService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SettingsPage::persistChanges() saves a Toggle field's OFF state as the
 * literal string 'false' (not '0' — see SettingsPageNoPhantomChangesTest's
 * "Bug 2" for why: Livewire's live state for a boolean field always
 * re-serializes as "true"/"false"). Every remember*() cache-toggle check in
 * this class used a naive `! settings(...)` truthiness test — since any
 * non-empty string, including the literal 'false', is PHP-truthy, actually
 * switching "Cache Page Sections" / "Cache Brand/Manufacturer Queries" off
 * via the admin Performance settings page had ZERO effect: caching stayed
 * on regardless. Only ever worked by accident for the seeded default ('0'),
 * which happens to be one of PHP's specific falsy strings.
 */
class CacheServiceToggleTest extends TestCase
{
    use RefreshDatabase;

    private function disable(string $key): void
    {
        Setting::updateOrCreate(
            ['group' => 'performance', 'key' => $key],
            ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('performance');
    }

    #[Test]
    public function toggling_cache_sections_off_via_the_real_admin_saved_value_actually_disables_it(): void
    {
        $this->disable('cache_sections');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'fresh';
        };

        app(CacheService::class)->rememberSection('homepage', $callback);
        app(CacheService::class)->rememberSection('homepage', $callback);

        $this->assertSame(2, $calls, 'with caching off, every call must recompute — nothing gets cached');
        $this->assertNull(Cache::get('sections.homepage'));
    }

    #[Test]
    public function toggling_cache_manufacturers_off_via_the_real_admin_saved_value_actually_disables_it(): void
    {
        $this->disable('cache_manufacturers');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return collect();
        };

        app(CacheService::class)->rememberManufacturers($callback);
        app(CacheService::class)->rememberManufacturers($callback);

        $this->assertSame(2, $calls);
        $this->assertNull(Cache::get('manufacturers.active'));
    }

    #[Test]
    public function toggling_cache_conditions_off_actually_disables_it(): void
    {
        $this->disable('cache_conditions');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return collect();
        };

        app(CacheService::class)->rememberActiveConditions($callback);
        app(CacheService::class)->rememberActiveConditions($callback);
        app(CacheService::class)->rememberConditionsBySlug($callback);
        app(CacheService::class)->rememberConditionsBySlug($callback);

        $this->assertSame(4, $calls);
        $this->assertNull(Cache::get('conditions.active'));
        $this->assertNull(Cache::get('conditions.by_slug'));
    }

    #[Test]
    public function toggling_cache_homepage_stats_off_actually_disables_it(): void
    {
        $this->disable('cache_homepage_stats');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return [];
        };

        app(CacheService::class)->rememberHeroStats($callback);
        app(CacheService::class)->rememberHeroStats($callback);
        app(CacheService::class)->rememberPopularOems($callback);
        app(CacheService::class)->rememberPopularOems($callback);

        $this->assertSame(4, $calls);
        $this->assertNull(Cache::get('hero.stats'));
        $this->assertNull(Cache::get('hero.popular_oems'));
    }

    #[Test]
    public function toggling_cache_search_console_stats_off_actually_disables_it(): void
    {
        $this->disable('cache_search_console_stats');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return [];
        };

        app(CacheService::class)->rememberSearchConsoleStats($callback);
        app(CacheService::class)->rememberSearchConsoleStats($callback);

        $this->assertSame(2, $calls);
        $this->assertNull(Cache::get('search_console_stats'));
    }

    #[Test]
    public function the_seeded_default_still_enables_caching(): void
    {
        // Sanity check the fix didn't flip the DEFAULT behavior — no row at
        // all falls through to settings()'s own default (true).
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'fresh';
        };

        app(CacheService::class)->rememberSection('homepage', $callback);
        app(CacheService::class)->rememberSection('homepage', $callback);

        $this->assertSame(1, $calls, 'the second call must be served from cache');
    }
}
