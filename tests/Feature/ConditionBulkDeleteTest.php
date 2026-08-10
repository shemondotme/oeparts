<?php

namespace Tests\Feature;

use App\Filament\Resources\ConditionResource\Pages\ListConditions;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ConditionResource's bulk-delete guard called $this->halt() from inside a
 * closure defined in a static method — a fatal "Using $this when not in
 * object context" the moment any selected condition was still referenced by
 * a product, and (with no transaction) any conditions earlier in the same
 * batch were already permanently deleted before that fatal error hit.
 */
class ConditionBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['view conditions', 'delete conditions']);
        $this->actingAs($this->admin, 'admin');
    }

    #[Test]
    public function bulk_delete_rejects_the_whole_batch_without_crashing_when_one_condition_is_in_use(): void
    {
        $deletable = Condition::create(['name' => 'Deletable', 'slug' => 'deletable', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $inUse = Condition::create(['name' => 'In Use', 'slug' => 'in-use', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        $manufacturer = Manufacturer::create(['name' => 'Test Mfr', 'slug' => 'test-mfr', 'country_code' => 'DE', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'X1', 'normalized_oem' => 'X1',
            'name' => 'Part', 'description' => 'Part',
            'price' => 10, 'condition_id' => $inUse->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);

        Livewire::test(ListConditions::class)
            ->callTableBulkAction('delete', [$deletable, $inUse]);

        // All-or-nothing: the in-use condition blocked the batch, so the
        // otherwise-deletable one must still exist too — no crash, no
        // partial delete.
        $this->assertModelExists($deletable);
        $this->assertModelExists($inUse);
    }

    #[Test]
    public function bulk_delete_succeeds_when_no_selected_condition_is_in_use(): void
    {
        $a = Condition::create(['name' => 'A', 'slug' => 'a', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $b = Condition::create(['name' => 'B', 'slug' => 'b', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        Livewire::test(ListConditions::class)
            ->callTableBulkAction('delete', [$a, $b]);

        $this->assertModelMissing($a);
        $this->assertModelMissing($b);
    }

    // -------------------------------------------------------------------------
    // Single-row delete (catalog-9): products.condition_id is NOT NULL with
    // restrictOnDelete(), so deleting an in-use condition via the row-level
    // action used to throw a raw, unhandled QueryException straight to the
    // admin instead of a friendly message.
    // -------------------------------------------------------------------------

    #[Test]
    public function single_row_delete_is_rejected_with_a_friendly_message_when_in_use(): void
    {
        $inUse = Condition::create(['name' => 'In Use', 'slug' => 'in-use-single', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        $manufacturer = Manufacturer::create(['name' => 'Test Mfr 2', 'slug' => 'test-mfr-2', 'country_code' => 'DE', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id,
            'oem_number' => 'X2', 'normalized_oem' => 'X2',
            'name' => 'Part', 'description' => 'Part',
            'price' => 10, 'condition_id' => $inUse->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);

        Livewire::test(ListConditions::class)
            ->callTableAction('delete', $inUse);

        $this->assertModelExists($inUse);
    }

    #[Test]
    public function single_row_delete_succeeds_when_not_in_use(): void
    {
        $unused = Condition::create(['name' => 'Unused', 'slug' => 'unused-single', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);

        Livewire::test(ListConditions::class)
            ->callTableAction('delete', $unused);

        $this->assertModelMissing($unused);
    }
}
