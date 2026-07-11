<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Admin;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Performance sweep fix: the Products list search box searched raw `oem_number`
 * (unindexed leading-wildcard LIKE), not `normalized_oem` (rule #12's BTREE-indexed
 * column). It also couldn't match a dashes/spaces-formatted query the way the
 * storefront and the global command-palette search already do.
 */
class ProductOemSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function search_matches_normalized_oem_ignoring_dashes_and_spaces(): void
    {
        $target = Product::factory()->create([
            'oem_number' => '06L-906-036-L',
            'normalized_oem' => '06L906036L',
        ]);
        $other = Product::factory()->create([
            'oem_number' => 'ALF000002',
            'normalized_oem' => 'ALF000002',
        ]);

        Livewire::test(ListProducts::class)
            ->loadTable()
            ->searchTable('06l 906 036 l')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function search_with_no_normalizable_characters_matches_nothing(): void
    {
        Product::factory()->create(['oem_number' => 'ALF000002', 'normalized_oem' => 'ALF000002']);

        Livewire::test(ListProducts::class)
            ->loadTable()
            ->searchTable('---')
            ->assertCountTableRecords(0);
    }
}
