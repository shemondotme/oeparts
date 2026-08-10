<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GET /api/v1/parts/{oem}/supersessions always returned an empty
 * "supersessions": [] with a 200 status — products.superseded_by_id doesn't
 * exist anywhere in the schema, so the chain-walk loop never executed. A
 * documented public API endpoint silently never did what it claimed, with
 * no error to signal the problem. Removed rather than leaving it lying.
 */
class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_dead_supersessions_endpoint_is_gone(): void
    {
        $condition = Condition::create(['name' => 'New', 'slug' => 'new-cat-api', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $manufacturer = Manufacturer::create(['name' => 'Mfr', 'slug' => 'mfr-cat-api', 'country_code' => 'DE', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'S1', 'normalized_oem' => 'S1',
            'name' => 'Part', 'description' => 'Part',
            'price' => 10, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $this->getJson('/api/v1/parts/S1/supersessions')->assertNotFound();
    }
}
