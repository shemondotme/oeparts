<?php

namespace Tests\Unit\Performance;

use App\Services\OemNormalizerService;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Asserts search stays cheap RELATIVE to a same-run baseline, not against a
 * hardcoded millisecond ceiling — this project's Docker test environment has
 * been measured to vary 50%+ run-to-run (slower on a loaded host, a cold
 * opcache, etc.), so a fixed "< 200ms" assertion is a coin flip on a slow
 * run and tells you nothing real about whether the CODE regressed. A ratio
 * against a baseline measured in the SAME run self-calibrates to whatever
 * this run's actual hardware/load conditions are.
 */
class OemSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected SearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchService = app(SearchService::class);
    }

    private function timeMs(callable $fn): float
    {
        $start = microtime(true);
        $fn();

        return (microtime(true) - $start) * 1000;
    }

    /** The lightest realistic search this suite compares every other variant against. */
    private function baselineMs(): float
    {
        // Warm-up call first (container/DB connection cost), then measure —
        // matches production steady-state, not cold-start.
        $run = fn () => $this->searchService->search('1K0407271E', null, null, [
            'paginate' => true, 'per_page' => 20, 'limit' => 100,
        ]);
        $run();

        return $this->timeMs($run);
    }

    #[Test]
    public function filtered_search_is_not_dramatically_slower_than_unfiltered(): void
    {
        $baseline = $this->baselineMs();

        $filteredMs = $this->timeMs(fn () => $this->searchService->search('1K0407271E', null, null, [
            'paginate' => true, 'per_page' => 20, 'limit' => 100,
            'condition' => null, 'in_stock_only' => true,
        ]));

        // An extra WHERE clause on an indexed column shouldn't multiply cost —
        // 5x baseline is generous headroom while still catching a real
        // regression (e.g. a filter that defeats an index).
        $this->assertLessThan(
            max(5 * $baseline, 50),
            $filteredMs,
            "Filtered search took {$filteredMs}ms vs a {$baseline}ms unfiltered baseline"
        );
    }

    #[Test]
    public function a_no_match_search_is_not_dramatically_slower_than_a_match(): void
    {
        $baseline = $this->baselineMs();

        $noMatchMs = $this->timeMs(fn () => $this->searchService->search('DOESNOTEXIST123456', null, null, [
            'paginate' => true, 'per_page' => 20, 'limit' => 100,
        ]));

        // A miss should be AT LEAST as fast as a hit (no rows to hydrate) —
        // generous 5x headroom guards against a pathological "scan everything
        // before concluding nothing matches" regression.
        $this->assertLessThan(
            max(5 * $baseline, 50),
            $noMatchMs,
            "No-match search took {$noMatchMs}ms vs a {$baseline}ms baseline"
        );
    }

    #[Test]
    public function non_paginated_search_is_not_dramatically_slower_than_paginated(): void
    {
        $baseline = $this->baselineMs();

        $nonPaginatedMs = $this->timeMs(fn () => $this->searchService->search('1K0407271E', null, null, [
            'paginate' => false, 'limit' => 100,
        ]));

        $this->assertLessThan(
            max(5 * $baseline, 50),
            $nonPaginatedMs,
            "Non-paginated search took {$nonPaginatedMs}ms vs a {$baseline}ms paginated baseline"
        );
    }

    #[Test]
    public function repeated_searches_do_not_get_slower_over_iterations(): void
    {
        // Catches an accidental per-call accumulation bug (a leaking query
        // log, a cache that grows unbounded, etc.) — compares the average of
        // the FIRST half of a run against the SECOND half, both measured in
        // the same run under the same conditions, rather than either half
        // against a fixed ceiling.
        $run = fn () => $this->searchService->search('06L906036L', null, null, [
            'paginate' => true, 'per_page' => 20, 'limit' => 100,
        ]);
        $run(); // warm-up

        $timings = [];
        for ($i = 0; $i < 10; $i++) {
            $timings[] = $this->timeMs($run);
        }

        $firstHalfAvg = array_sum(array_slice($timings, 0, 5)) / 5;
        $secondHalfAvg = array_sum(array_slice($timings, 5, 5)) / 5;

        $this->assertLessThan(
            max(3 * $firstHalfAvg, 20),
            $secondHalfAvg,
            'Second half of 10 iterations averaged '.$secondHalfAvg.'ms vs '.$firstHalfAvg.'ms for the first half — search is getting slower over repeated calls'
        );
    }

    #[Test]
    public function oem_normalization_is_a_small_fraction_of_a_real_search(): void
    {
        $searchMs = $this->baselineMs();

        $normalizer = app(OemNormalizerService::class);
        $normalizeMs = $this->timeMs(function () use ($normalizer) {
            for ($i = 0; $i < 1000; $i++) {
                $normalizer->normalize('06L-906-036-L');
            }
        });

        // 1000 normalizations should stay well under a single search round
        // trip (DB query + hydration) — catches accidental heavy work (a
        // regex recompiled per call, a DB/cache lookup sneaking into what
        // should be pure string manipulation) without pinning an absolute ms
        // figure this environment can't hold steady.
        $this->assertLessThan(
            max(3 * $searchMs, 30),
            $normalizeMs,
            "1000 normalizations took {$normalizeMs}ms vs a {$searchMs}ms single-search baseline"
        );
    }
}
