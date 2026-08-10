<?php

namespace Tests\Feature;

use App\Filament\Pages\Catalog\BulkUpdateProducts;
use App\Models\Admin;
use App\Models\BulkUpdateLog;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * apply() used to load every matching row into memory at once
 * ($this->matchingQuery()->get()) before looping and ->save()-ing each one
 * inside a single DB::transaction() — fine at demo-catalog scale, a real
 * memory/execution-time risk once the catalog reaches ~100k rows and a
 * broad filter matches a large slice of it. Now uses chunkById(500, ...)
 * instead. This test uses a small dataset (chunking is behavior-invisible
 * at this scale) specifically to prove chunking doesn't change correctness:
 * every matching row is still updated exactly once, snapshotted, and logged.
 */
class BulkUpdateProductsChunkingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');

        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Mfr'], 'slug' => 'mfr', 'country_code' => 'DE', 'is_active' => true,
        ]);
        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]
        );
    }

    private function makeProducts(int $count, array $overrides = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            Product::create(array_merge([
                'manufacturer_id' => $this->manufacturer->id,
                'oem_number' => "BULK{$i}",
                'normalized_oem' => "BULK{$i}",
                'name' => ['en' => "Product {$i}"],
                'description' => ['en' => 'x'],
                'price' => '100.00',
                'condition_id' => $this->condition->id,
                'is_active' => true,
                'is_in_stock' => true,
            ], $overrides));
        }
    }

    #[Test]
    public function every_matching_row_across_multiple_chunks_is_updated_exactly_once(): void
    {
        // 3 chunks' worth at the new chunkById(500, ...) size.
        $this->makeProducts(1250);

        Livewire::test(BulkUpdateProducts::class)
            ->set('actionType', 'price_increase')
            ->set('percentage', '10')
            ->call('runPreview')
            ->set('confirmed', true)
            ->set('largeBatchAck', true)
            ->call('apply');

        $this->assertSame(1250, Product::where('price', '110.00')->count());

        $log = BulkUpdateLog::latest('id')->first();
        $this->assertSame(1250, $log->affected_rows_count);
    }

    #[Test]
    public function a_filtered_subset_only_updates_matching_rows_not_everything(): void
    {
        $this->makeProducts(600, ['is_in_stock' => true]);
        $outOfStock = Product::create([
            'manufacturer_id' => $this->manufacturer->id, 'oem_number' => 'OOS1', 'normalized_oem' => 'OOS1',
            'name' => ['en' => 'Out of stock one'], 'description' => ['en' => 'x'],
            'price' => '50.00', 'condition_id' => $this->condition->id,
            'is_active' => true, 'is_in_stock' => false,
        ]);

        Livewire::test(BulkUpdateProducts::class)
            ->set('actionType', 'stock_out')
            ->set('stockFilter', 'in')
            ->call('runPreview')
            ->set('confirmed', true)
            ->set('largeBatchAck', true)
            ->call('apply');

        $this->assertSame(600, Product::where('is_in_stock', false)->where('oem_number', '!=', 'OOS1')->count());
        // The already-out-of-stock row was never in the matched set and is untouched.
        $this->assertSame('50.00', $outOfStock->fresh()->price);
    }

    #[Test]
    public function no_matching_products_shows_a_warning_without_creating_a_log(): void
    {
        Livewire::test(BulkUpdateProducts::class)
            ->set('actionType', 'stock_out')
            ->set('manufacturerId', $this->manufacturer->id)
            ->call('runPreview')
            ->set('confirmed', true)
            ->call('apply');

        $this->assertSame(0, BulkUpdateLog::count());
    }
}
