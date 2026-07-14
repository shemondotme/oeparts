<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductImportButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);
    }

    #[Test]
    public function empty_state_import_action_is_visible_for_authorized_role(): void
    {
        // Regression test for Option G: this empty-state button previously had
        // no ->action()/->url() at all and did nothing when clicked. It now
        // links to the ProductImport page (Bulk Import redesign), renamed to
        // avoid colliding with the header action of the same name.
        $manager = Admin::factory()->create();
        $manager->assignRole('manager'); // has 'import products'
        $this->actingAs($manager, 'admin');

        Livewire::test(ListProducts::class)
            ->assertTableActionExists('importCsvEmpty')
            ->assertTableActionVisible('importCsvEmpty');
    }

    #[Test]
    public function empty_state_import_action_is_hidden_without_import_permission(): void
    {
        $support = Admin::factory()->create();
        $support->assignRole('support'); // no 'import products'
        $this->actingAs($support, 'admin');

        Livewire::test(ListProducts::class)->assertTableActionHidden('importCsvEmpty');
    }
}
