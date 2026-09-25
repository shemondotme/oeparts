<?php

namespace Tests\Feature;

use App\Support\NavBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sidebar renders every resource's badge on EVERY admin page. A badge for a
 * table a release adds threw "table doesn't exist" for the whole admin panel in
 * the window right after a self-update swapped in new code but before its
 * migrations ran — including from the page that finishes the update (found by
 * rehearsing the real 1.0.16 -> 2.0.0 self-update).
 */
class NavBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function it_returns_the_count_as_a_string_and_hides_zero(): void
    {
        $this->assertSame('3', NavBadge::count('t_three', fn () => 3));
        $this->assertNull(NavBadge::count('t_zero', fn () => 0));
    }

    #[Test]
    public function it_caches_a_successful_count(): void
    {
        $calls = 0;
        $count = function () use (&$calls) {
            $calls++;

            return 5;
        };

        NavBadge::count('t_cached', $count);
        NavBadge::count('t_cached', $count);

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function a_missing_table_hides_the_badge_instead_of_breaking_the_page(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn ($m) => str_contains($m, 'Navigation badge "t_missing" skipped'));

        $badge = NavBadge::count('t_missing', fn () => DB::table('a_table_the_release_has_not_migrated_yet')->count());

        $this->assertNull($badge);
    }

    #[Test]
    public function a_failed_badge_is_not_cached_so_it_returns_once_the_schema_catches_up(): void
    {
        Log::shouldReceive('warning')->once();

        $this->assertNull(NavBadge::count('t_recovers', fn () => DB::table('not_there_yet')->count()));

        // Same key, table now "exists": must be recomputed, not served from a cached failure.
        $this->assertSame('2', NavBadge::count('t_recovers', fn () => 2));
    }
}
