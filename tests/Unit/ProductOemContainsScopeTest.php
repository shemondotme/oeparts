<?php

namespace Tests\Unit;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Product::scopeOemContains() replaces a leading-wildcard
 * `normalized_oem LIKE "%term%"` (can't use a plain BTREE index — a full
 * table scan on every partial/substring OEM lookup) with a MySQL FULLTEXT
 * ngram index lookup in production, falling back to the original LIKE on
 * SQLite (this test DB) since ngram FULLTEXT has no SQLite equivalent.
 *
 * The MySQL/ngram path itself was verified empirically against a real
 * MySQL 8.0 instance (not exercisable from this SQLite-backed suite) —
 * substring matches like "906036" against "06L906036L", and even a
 * 2-character fragment like "4F" against "4F0698151", correctly matched
 * only the rows actually containing that substring. This test proves the
 * scope's observable behavior (which is driver-independent) is correct.
 */
class ProductOemContainsScopeTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Mfr'], 'slug' => 'mfr', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]
        );
    }

    private function product(string $oem): Product
    {
        return Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => $oem, 'normalized_oem' => $oem,
            'name' => ['en' => 'x'], 'description' => ['en' => 'x'], 'price' => 10,
            'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
    }

    #[Test]
    public function matches_a_substring_in_the_middle_of_the_oem(): void
    {
        $match = $this->product('06L906036L');
        $noMatch = $this->product('1K0615301AA');

        $results = Product::query()->oemContains('906036')->pluck('id');

        $this->assertTrue($results->contains($match->id));
        $this->assertFalse($results->contains($noMatch->id));
    }

    #[Test]
    public function matches_a_short_prefix_fragment(): void
    {
        $match = $this->product('4F0698151');
        $noMatch = $this->product('06L906036L');

        $results = Product::query()->oemContains('4F')->pluck('id');

        $this->assertTrue($results->contains($match->id));
        $this->assertFalse($results->contains($noMatch->id));
    }

    #[Test]
    public function returns_no_rows_for_a_term_that_matches_nothing(): void
    {
        $this->product('06L906036L');

        $results = Product::query()->oemContains('ZZZZZZ')->pluck('id');

        $this->assertCount(0, $results);
    }

    #[Test]
    public function an_empty_term_returns_no_rows_instead_of_matching_everything(): void
    {
        $this->product('06L906036L');

        $results = Product::query()->oemContains('')->pluck('id');

        $this->assertCount(0, $results);
    }
}
