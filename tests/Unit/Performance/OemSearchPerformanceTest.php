<?php

namespace Tests\Unit\Performance;

use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OemSearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected SearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchService = app(SearchService::class);
    }

    #[Test]
    public function oem_search_completes_in_under_200ms(): void
    {
        // Target: OEM search should complete in < 200ms (per PRD)
        $startTime = microtime(true);

        $this->searchService->search('1K0407271E', null, null, [
            'paginate' => true,
            'per_page' => 20,
            'limit' => 100,
        ]);

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(200, $elapsedMs, "Search took {$elapsedMs}ms, must be under 200ms");
    }

    #[Test]
    public function oem_search_averages_under_50ms_over_10_iterations(): void
    {
        // Typical: OEM search averages ~12ms paginated, ~54ms non-paginated
        $totalTime = 0;
        $iterations = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $this->searchService->search('06L906036L', null, null, [
                'paginate' => true,
                'per_page' => 20,
                'limit' => 100,
            ]);

            $totalTime += (microtime(true) - $startTime) * 1000;
        }

        $avgMs = $totalTime / $iterations;
        $this->assertLessThan(50, $avgMs, "Average search took {$avgMs}ms, should be under 50ms");
    }

    #[Test]
    public function oem_search_with_filters_completes_in_under_200ms(): void
    {
        // Filtered searches should also stay under 200ms
        $startTime = microtime(true);

        $this->searchService->search('1K0407271E', null, null, [
            'paginate' => true,
            'per_page' => 20,
            'limit' => 100,
            'condition' => null,
            'in_stock_only' => true,
        ]);

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(200, $elapsedMs, "Filtered search took {$elapsedMs}ms, must be under 200ms");
    }

    #[Test]
    public function non_existent_oem_search_is_fast(): void
    {
        // Searches that find nothing should also be fast
        $startTime = microtime(true);

        $this->searchService->search('DOESNOTEXIST123456', null, null, [
            'paginate' => true,
            'per_page' => 20,
            'limit' => 100,
        ]);

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(200, $elapsedMs, "No-result search took {$elapsedMs}ms, must be under 200ms");
    }

    #[Test]
    public function non_paginated_search_completes_in_under_200ms(): void
    {
        // Non-paginated searches (internal use) should also stay under 200ms
        $startTime = microtime(true);

        $this->searchService->search('1K0407271E', null, null, [
            'paginate' => false,
            'limit' => 100,
        ]);

        $elapsedMs = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(200, $elapsedMs, "Non-paginated search took {$elapsedMs}ms, must be under 200ms");
    }

    #[Test]
    public function oem_normalization_is_negligible(): void
    {
        // OEM normalization should be < 5ms for 1000 iterations (~0.005ms per normalize)
        $normalizer = app(\App\Services\OemNormalizerService::class);
        $startTime = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $normalizer->normalize('06L-906-036-L');
        }

        $totalMs = (microtime(true) - $startTime) * 1000;
        $avgPerNormalize = $totalMs / 1000;

        // With 1000 iterations, even at 0.003ms per normalize we're under 5ms
        $this->assertLessThan(5, $totalMs, "1000 normalizations took {$totalMs}ms");
    }
}
