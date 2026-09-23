<?php

namespace Tests\Feature;

use App\Models\CarModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 21 (Search/Filter/Fitment Data Accuracy). GET /api/car-models's
 * ?year= filter — CarModelResource's own form declares BOTH year_from and
 * year_to nullable (year_to's helper text: "Leave empty if still in
 * production"; year_from has no equivalent guidance, but the admin table's
 * own display logic already anticipates a null year_from alongside a set
 * year_to, e.g. a model whose exact debut year isn't documented but whose
 * discontinuation year is) — yet the query only ever guarded year_to's NULL
 * case. `NULL <= $year` evaluates to unknown/false in SQL, so a car model
 * with an unset year_from was silently excluded from EVERY year-filtered
 * lookup forever, even though leaving it blank is an explicitly supported,
 * legitimate admin choice. Zero test coverage existed for this endpoint
 * before this file.
 */
class CarModelYearFilterTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manufacturer = Manufacturer::create([
            'name' => 'BMW', 'slug' => 'bmw-year-filter', 'country_code' => 'DE', 'is_active' => true,
        ]);
    }

    private function makeCarModel(array $overrides = []): CarModel
    {
        static $i = 0;
        $i++;

        return CarModel::create(array_merge([
            'manufacturer_id' => $this->manufacturer->id,
            'name' => "Model {$i}",
            'slug' => "model-{$i}",
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    #[Test]
    public function a_year_exactly_equal_to_year_from_is_included_inclusive_lower_bound(): void
    {
        $model = $this->makeCarModel(['year_from' => 2015, 'year_to' => 2020]);

        $ids = $this->getJson('/api/car-models?year=2015')->json('data.*.id');

        $this->assertContains($model->id, $ids);
    }

    #[Test]
    public function a_year_exactly_equal_to_year_to_is_included_inclusive_upper_bound(): void
    {
        $model = $this->makeCarModel(['year_from' => 2015, 'year_to' => 2020]);

        $ids = $this->getJson('/api/car-models?year=2020')->json('data.*.id');

        $this->assertContains($model->id, $ids);
    }

    #[Test]
    public function a_year_outside_the_range_is_excluded(): void
    {
        $model = $this->makeCarModel(['year_from' => 2015, 'year_to' => 2020]);

        $this->assertNotContains($model->id, $this->getJson('/api/car-models?year=2014')->json('data.*.id'));
        $this->assertNotContains($model->id, $this->getJson('/api/car-models?year=2021')->json('data.*.id'));
    }

    #[Test]
    public function a_null_year_to_means_still_in_production_and_matches_any_later_year(): void
    {
        $model = $this->makeCarModel(['year_from' => 2018, 'year_to' => null]);

        $ids = $this->getJson('/api/car-models?year=2030')->json('data.*.id');

        $this->assertContains($model->id, $ids);
    }

    /**
     * The actual bug this phase found and fixed: before the fix, this
     * exact scenario returned an empty result — a real, admin-form-
     * supported data state (documented discontinuation year, undocumented
     * debut year) silently vanished from every year-filtered lookup.
     */
    #[Test]
    public function a_null_year_from_means_debut_year_unknown_and_matches_any_earlier_year(): void
    {
        $model = $this->makeCarModel(['year_from' => null, 'year_to' => 2010]);

        $ids = $this->getJson('/api/car-models?year=1995')->json('data.*.id');

        $this->assertContains($model->id, $ids);
    }

    #[Test]
    public function both_years_null_matches_any_year(): void
    {
        $model = $this->makeCarModel(['year_from' => null, 'year_to' => null]);

        $ids = $this->getJson('/api/car-models?year=1970')->json('data.*.id');

        $this->assertContains($model->id, $ids);
    }

    #[Test]
    public function the_manufacturer_filter_combines_correctly_with_the_year_filter(): void
    {
        $otherManufacturer = Manufacturer::create([
            'name' => 'Audi', 'slug' => 'audi-year-filter', 'country_code' => 'DE', 'is_active' => true,
        ]);

        $matching = $this->makeCarModel(['year_from' => 2015, 'year_to' => 2020]);
        $wrongManufacturer = $this->makeCarModel(['manufacturer_id' => $otherManufacturer->id, 'year_from' => 2015, 'year_to' => 2020]);
        $wrongYear = $this->makeCarModel(['year_from' => 2000, 'year_to' => 2005]);

        $ids = $this->getJson("/api/car-models?manufacturer_id={$this->manufacturer->id}&year=2017")->json('data.*.id');

        $this->assertContains($matching->id, $ids);
        $this->assertNotContains($wrongManufacturer->id, $ids);
        $this->assertNotContains($wrongYear->id, $ids);
    }

    #[Test]
    public function an_inactive_car_model_is_never_returned_regardless_of_year(): void
    {
        $model = $this->makeCarModel(['year_from' => 2015, 'year_to' => 2020, 'is_active' => false]);

        $ids = $this->getJson('/api/car-models?year=2017')->json('data.*.id');

        $this->assertNotContains($model->id, $ids);
    }
}
