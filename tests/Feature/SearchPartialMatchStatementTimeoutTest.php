<?php

namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The partial (substring) lookup now runs inside a per-session statement time limit on MySQL, so the COUNT(*) of a
 * paginated page is bounded too. On the test database (SQLite) there is no such limit and the search must behave
 * exactly as before: a partial number still finds its part, and a number that matches nothing still ends in the
 * normal zero-result answer.
 */
class SearchPartialMatchStatementTimeoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_paginated_partial_search_still_finds_the_part_and_a_miss_stays_a_miss(): void
    {
        $brand = Manufacturer::factory()->create();
        Product::factory()->create(['manufacturer_id' => $brand->id, 'oem_number' => 'A2076806393', 'normalized_oem' => 'A2076806393', 'is_active' => true]);

        $found = app(SearchService::class)->search('2076806', null, null, ['paginate' => true, 'per_page' => 20], false);
        $missed = app(SearchService::class)->search('99999999999', null, null, ['paginate' => true, 'per_page' => 20], false);

        $this->assertSame('partial', $found['search_type']);
        $this->assertSame(1, $found['total']);
        $this->assertSame(0, $missed['total']);
    }
}
