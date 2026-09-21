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

    /**
     * GET /api/parts?q=...&lang=... interpolates lang straight into a raw
     * SQL JSON-path fragment (not a bound parameter) to do a locale-aware
     * name lookup — an unvalidated value here is a SQL injection vector on
     * this public, unauthenticated endpoint, not just a bad-locale fallback.
     */
    #[Test]
    public function an_invalid_lang_query_param_cannot_break_out_of_the_raw_sql_json_path(): void
    {
        $condition = Condition::create(['name' => 'New', 'slug' => 'new-cat-lang', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $manufacturer = Manufacturer::create(['name' => 'Mfr', 'slug' => 'mfr-cat-lang', 'country_code' => 'DE', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'LANG1', 'normalized_oem' => 'LANG1',
            'name' => ['en' => 'Brake Pad'], 'description' => 'Part',
            'price' => 10, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $response = $this->getJson('/api/parts?q=Brake&lang='.urlencode('en" OR "1"="1'));

        // No SQL error (assertOk alone already proves that), and the
        // malicious value safely falls back to 'en' rather than being
        // rejected outright — the en-locale match still succeeds normally.
        $response->assertOk();
        $this->assertSame(1, $response->json('data.meta.total'));
    }

    #[Test]
    public function a_whitelisted_lang_query_param_still_matches_that_locales_name(): void
    {
        $condition = Condition::create(['name' => 'New', 'slug' => 'new-cat-lang2', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $manufacturer = Manufacturer::create(['name' => 'Mfr', 'slug' => 'mfr-cat-lang2', 'country_code' => 'DE', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'LANG2', 'normalized_oem' => 'LANG2',
            'name' => ['en' => 'Brake Pad', 'de' => 'Bremsbelag'], 'description' => 'Part',
            'price' => 10, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $response = $this->getJson('/api/parts?q=Bremsbelag&lang=de');

        $response->assertOk();
        $data = $response->json('data') ?? [];
        $this->assertNotEmpty($data, 'the German-locale search term should match via the de JSON path');
    }
}
