<?php

namespace Tests\Feature;

use App\Livewire\JumpToOem;
use App\Models\Admin;
use App\Models\CarModel;
use App\Models\FailedSearchLog;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\SearchLog;
use App\Services\AdminNavService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JumpToOemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);
    }

    private function activeAdmin(): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    #[Test]
    public function exact_oem_query_returns_the_matching_product_tagged_exact(): void
    {
        $admin = $this->activeAdmin();
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        $product = Product::factory()->create([
            'oem_number' => '06L-906-036-L',
            'normalized_oem' => '06L906036L',
        ]);

        Livewire::test(JumpToOem::class)
            ->set('oem', '06L-906-036-L')
            ->assertSet('searchType', 'exact')
            ->assertSee($product->oem_number);
    }

    #[Test]
    public function cross_reference_only_query_returns_results_tagged_cross_reference(): void
    {
        $admin = $this->activeAdmin();
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        $product = Product::factory()->create([
            'oem_number' => 'AAA111',
            'normalized_oem' => 'AAA111',
        ]);

        ProductCrossReference::create([
            'product_id' => $product->id,
            'cross_oem_number' => 'XREF-999',
            'normalized_cross_oem' => 'XREF999',
        ]);

        Livewire::test(JumpToOem::class)
            ->set('oem', 'XREF-999')
            ->assertSet('searchType', 'cross_reference')
            ->assertSee($product->oem_number);
    }

    #[Test]
    public function partial_query_returns_multiple_results_tagged_partial(): void
    {
        $admin = $this->activeAdmin();
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Product::factory()->create(['oem_number' => 'BMW1122A', 'normalized_oem' => 'BMW1122A']);
        Product::factory()->create(['oem_number' => 'BMW1122B', 'normalized_oem' => 'BMW1122B']);

        Livewire::test(JumpToOem::class)
            ->set('oem', 'BMW1122')
            ->assertSet('searchType', 'partial');
    }

    #[Test]
    public function no_match_query_returns_empty_results(): void
    {
        $admin = $this->activeAdmin();
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Livewire::test(JumpToOem::class)
            ->set('oem', 'NOPE12345')
            ->assertSet('searchType', 'none')
            ->assertSet('results', []);
    }

    #[Test]
    public function admin_lookup_does_not_create_search_log_or_failed_search_log_rows(): void
    {
        $admin = $this->activeAdmin();
        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Product::factory()->create(['oem_number' => 'LOGCHECK1', 'normalized_oem' => 'LOGCHECK1']);

        Livewire::test(JumpToOem::class)->set('oem', 'LOGCHECK1');
        Livewire::test(JumpToOem::class)->set('oem', 'TOTALLYMISSING');

        $this->assertSame(0, SearchLog::count());
        $this->assertSame(0, FailedSearchLog::count());
    }

    #[Test]
    public function empty_oem_shows_the_same_entries_as_valid_recent(): void
    {
        $admin = $this->activeAdmin();

        $carModel = CarModel::factory()->create(['name' => 'Golf GTI']);
        $url = url("/admin/car-models/{$carModel->id}/edit");
        AdminNavService::recordVisit($admin, $url, 'Golf GTI', $url, 'Catalog');

        $this->actingAs($admin, 'admin');
        $this->get('/admin');

        Livewire::test(JumpToOem::class)
            ->set('oem', '')
            ->assertSee('Golf GTI');
    }
}
