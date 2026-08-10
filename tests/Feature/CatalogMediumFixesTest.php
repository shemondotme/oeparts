<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Condition;
use App\Models\FailedSearchLog;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogMediumFixesTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Test Mfr'], 'slug' => 'test-mfr', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->condition = Condition::firstOrCreate(
            ['slug' => 'used'],
            ['name' => 'Used', 'bg_color' => '#fef2f2', 'text_color' => '#991b1b', 'is_active' => true]
        );
    }

    // ── catalog-2: FailedSearchLog now persists manufacturer/car-model context ──

    #[Test]
    public function failed_search_log_persists_manufacturer_and_car_model_context(): void
    {
        app(SearchService::class)->search('NO-SUCH-OEM-XYZ', $this->manufacturer->id, null, [], true);

        $log = FailedSearchLog::first();
        $this->assertNotNull($log);
        $this->assertSame($this->manufacturer->id, $log->manufacturer_id);
    }

    // ── catalog-3: product changes invalidate the search result cache ──

    #[Test]
    public function updating_a_product_invalidates_the_cached_search_result(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'CACHE123', 'normalized_oem' => 'CACHE123',
            'name' => ['en' => 'Original name'], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        $first = app(SearchService::class)->search('CACHE123');
        $this->assertSame(1, $first['total']);

        // A price change would normally leave a stale cached result behind.
        $product->update(['price' => 999]);

        $second = app(SearchService::class)->search('CACHE123');
        $this->assertEquals('999.00', (string) $second['products']->first()->price);
    }

    // ── catalog-4: autocomplete's condition_label is locale-aware ──

    #[Test]
    public function autocomplete_condition_label_is_translated_for_the_requested_locale(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'AC123456', 'normalized_oem' => 'AC123456',
            'name' => ['en' => 'A part'], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        $results = app(SearchService::class)->autocomplete('AC1234', 'de', 5);

        $this->assertNotEmpty($results);
        $this->assertSame('Gebraucht', $results[0]['condition_label']);
    }

    // ── catalog-10: soft-deleted products can be restored from the admin ──

    #[Test]
    public function a_super_admin_can_restore_a_soft_deleted_product(): void
    {
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'DEL123', 'normalized_oem' => 'DEL123',
            'name' => ['en' => 'Deletable'], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        $product->delete();
        $this->assertTrue($product->fresh()->trashed());

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $product);

        $this->assertFalse($product->fresh()->trashed());
    }

    // ── catalog-15: API part search matches the requested locale, not raw JSON ──

    #[Test]
    public function api_parts_search_matches_the_requested_locale_name_not_the_raw_json_blob(): void
    {
        Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'JS123456', 'normalized_oem' => 'JS123456',
            'name' => ['en' => 'Brake Pad', 'de' => 'Bremsbelag'], 'description' => ['en' => 'x'],
            'price' => 10, 'condition_id' => $this->condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        // Searching the German term with lang=de matches via the de key...
        $de = $this->getJson('/api/parts?q=Bremsbelag&lang=de');
        $de->assertStatus(200);
        $this->assertNotEmpty($de->json('data.items'));

        // ...but the same German term does NOT match when searching under
        // the English locale, proving the match is scoped to the requested
        // locale's value rather than a raw LIKE across the whole JSON blob
        // (which would match "Bremsbelag" regardless of which locale was
        // asked for, since it's present in the encoded text either way).
        $en = $this->getJson('/api/parts?q=Bremsbelag&lang=en');
        $this->assertEmpty($en->json('data.items'));
    }
}
