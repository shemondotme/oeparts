<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 21 (Search/Filter/Fitment Data Accuracy). OemSearchTest's existing
 * car-model filter test only ever proves the happy path doesn't crash (one
 * product, one model, filtered request returns 200 and shows the model
 * chip) — it never proves the filter actually EXCLUDES a non-matching
 * product, never exercises a product fitting MULTIPLE car models (a real,
 * common case — e.g. a shared part across several trims/years), and never
 * combines the car-model filter with another filter at once. All three are
 * the plan's own explicit wording for this phase ("multi-model products,
 * manufacturer filtering").
 */
class SearchCarModelFilterCombinationTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturerA;

    private Manufacturer $manufacturerB;

    private Condition $conditionNew;

    private Condition $conditionUsed;

    private CarModel $modelX;

    private CarModel $modelY;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conditionNew = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->conditionUsed = Condition::firstOrCreate(
            ['slug' => 'used'],
            ['name' => 'Used', 'bg_color' => '#fef2f2', 'text_color' => '#991b1b', 'is_active' => true]
        );

        $this->manufacturerA = Manufacturer::create([
            'name' => 'Manufacturer A', 'slug' => 'mfr-a-carmodel-filter', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->manufacturerB = Manufacturer::create([
            'name' => 'Manufacturer B', 'slug' => 'mfr-b-carmodel-filter', 'country_code' => 'DE', 'is_active' => true,
        ]);

        $this->modelX = CarModel::create([
            'manufacturer_id' => $this->manufacturerA->id, 'name' => 'Model X', 'slug' => 'model-x-filter', 'is_active' => true,
        ]);
        $this->modelY = CarModel::create([
            'manufacturer_id' => $this->manufacturerA->id, 'name' => 'Model Y', 'slug' => 'model-y-filter', 'is_active' => true,
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        static $i = 0;
        $i++;

        return Product::create(array_merge([
            'manufacturer_id' => $this->manufacturerA->id,
            'oem_number' => "CARFLT{$i}", 'normalized_oem' => "CARFLT{$i}",
            'condition_id' => $this->conditionNew->id, 'price' => '50.00',
            'is_in_stock' => true, 'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function a_product_fitting_multiple_car_models_matches_a_search_filtered_by_either_one(): void
    {
        $product = $this->makeProduct(['oem_number' => 'MULTIFIT1', 'normalized_oem' => 'MULTIFIT1']);
        $product->carModels()->attach([$this->modelX->id, $this->modelY->id]);

        $this->get("/en/parts/MULTIFIT1?model={$this->modelX->id}")
            ->assertStatus(200)->assertSeeText('MULTIFIT1');

        $this->get("/en/parts/MULTIFIT1?model={$this->modelY->id}")
            ->assertStatus(200)->assertSeeText('MULTIFIT1');
    }

    /**
     * The gap OemSearchTest's own existing test left open: it only ever
     * proved a MATCHING filter still returns the product, never that a
     * NON-matching filter actually excludes it — a broken/no-op filter
     * would have passed that test just as easily.
     */
    #[Test]
    public function a_product_not_fitting_the_filtered_car_model_is_excluded(): void
    {
        $fits = $this->makeProduct(['oem_number' => 'FITSX', 'normalized_oem' => 'FITSX']);
        $fits->carModels()->attach($this->modelX->id);

        $doesNotFit = $this->makeProduct(['oem_number' => 'FITSY', 'normalized_oem' => 'FITSY']);
        $doesNotFit->carModels()->attach($this->modelY->id);

        // Both share the same OEM prefix pattern loosely enough that a
        // partial/FULLTEXT match could otherwise surface either — search by
        // FITSX's own oem, filtered to Model Y (which it does NOT fit).
        // A real 404, not just an absent product listing: FITSX genuinely
        // exists, so this is only reachable if the model filter actually
        // excluded it rather than silently no-op'ing.
        $this->get("/en/parts/FITSX?model={$this->modelY->id}")->assertNotFound();
    }

    #[Test]
    public function the_car_model_filter_combines_with_the_manufacturer_filter_using_and_semantics(): void
    {
        $product = $this->makeProduct(['oem_number' => 'COMBOMFR', 'normalized_oem' => 'COMBOMFR', 'manufacturer_id' => $this->manufacturerA->id]);
        $product->carModels()->attach($this->modelX->id);

        // Correct manufacturer + correct model — matches.
        $this->get("/en/parts/COMBOMFR?manufacturer={$this->manufacturerA->id}&model={$this->modelX->id}")
            ->assertStatus(200)->assertSeeText('COMBOMFR');

        // Wrong manufacturer + correct model — AND semantics means this must
        // NOT match, even though the model filter alone would.
        $this->get("/en/parts/COMBOMFR?manufacturer={$this->manufacturerB->id}&model={$this->modelX->id}")
            ->assertNotFound();
    }

    /**
     * Unlike manufacturer/model (which fall through to a genuine 404 when
     * combined with an otherwise-real OEM — see the tests above),
     * SearchService's $hasActiveFilters deliberately covers only condition
     * and in_stock_only, landing on the softer "filtered empty" page (200,
     * matches OemSearchTest's own established
     * filtered_empty_state_when_condition_excludes_all_results pattern)
     * instead. Confirmed intentional (a clean, explicit `$condition ||
     * $inStockOnly` expression, not a forgotten parameter) — this test
     * pins the model filter combining correctly into that SAME existing
     * behavior, not proposing a behavior change.
     */
    #[Test]
    public function the_car_model_filter_combines_with_the_condition_filter_using_and_semantics(): void
    {
        $newPart = $this->makeProduct(['oem_number' => 'COMBOCONDA', 'normalized_oem' => 'COMBOCONDA', 'condition_id' => $this->conditionNew->id]);
        $newPart->carModels()->attach($this->modelX->id);

        // Same OEM prefix so a loose partial match would find it if the
        // condition filter were silently ignored.
        $usedPart = $this->makeProduct(['oem_number' => 'COMBOCONDB', 'normalized_oem' => 'COMBOCONDB', 'condition_id' => $this->conditionUsed->id]);
        $usedPart->carModels()->attach($this->modelX->id);

        $response = $this->get("/en/parts/COMBOCONDA?model={$this->modelX->id}&condition=used");

        $response->assertStatus(200);
        $response->assertSee(__('search.filtered_empty_title'), false);
    }

    #[Test]
    public function the_car_model_filter_combines_with_the_in_stock_filter_using_and_semantics(): void
    {
        $outOfStock = $this->makeProduct(['oem_number' => 'COMBOSTOCK', 'normalized_oem' => 'COMBOSTOCK', 'is_in_stock' => false]);
        $outOfStock->carModels()->attach($this->modelX->id);

        $response = $this->get("/en/parts/COMBOSTOCK?model={$this->modelX->id}&in_stock=1");

        $response->assertStatus(200);
        $response->assertSee(__('search.filtered_empty_title'), false);
    }
}
