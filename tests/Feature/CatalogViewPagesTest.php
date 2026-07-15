<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the "nobody ever mounted the View/Edit page" class of
 * bug (CLAUDE rule #38): the Catalog audit found 5 pages returning live 500s
 * that every list-only test had missed.
 */
class CatalogViewPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    public function test_product_view_page_renders_with_condition(): void
    {
        $condition = Condition::create([
            'name' => 'New', 'slug' => 'new',
            'bg_color' => '#DCFCE7', 'text_color' => '#166534',
            'is_active' => true, 'sort_order' => 0,
        ]);
        $product = Product::factory()->create(['condition_id' => $condition->id]);

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\ViewProduct::class, ['record' => $product->id])
            ->assertOk();
    }

    public function test_manufacturer_view_and_edit_pages_render(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => ['en' => 'Bosch', 'de' => 'Bosch GmbH']]);

        Livewire::test(\App\Filament\Resources\ManufacturerResource\Pages\ViewManufacturer::class, ['record' => $manufacturer->id])
            ->assertOk();
        Livewire::test(\App\Filament\Resources\ManufacturerResource\Pages\EditManufacturer::class, ['record' => $manufacturer->id])
            ->assertOk();
    }

    public function test_category_view_and_edit_pages_render(): void
    {
        $category = Category::create([
            'name' => ['en' => 'Brakes', 'de' => 'Bremsen'],
            'slug' => 'brakes',
            'sort_order' => 0,
        ]);

        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ViewCategory::class, ['record' => $category->id])
            ->assertOk();
        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\EditCategory::class, ['record' => $category->id])
            ->assertOk();
    }

    public function test_record_titles_resolve_json_names_to_strings(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => ['en' => 'Bosch']]);
        $category = Category::create(['name' => ['en' => 'Brakes'], 'slug' => 'brakes', 'sort_order' => 0]);

        $this->assertSame('Bosch', \App\Filament\Resources\ManufacturerResource::getRecordTitle($manufacturer));
        $this->assertSame('Brakes', \App\Filament\Resources\CategoryResource::getRecordTitle($category));
    }

    public function test_stock_and_visibility_changes_invalidate_homepage_cache(): void
    {
        $product = Product::factory()->create(['is_in_stock' => true, 'is_active' => true]);

        Cache::put('sections.homepage', 'cached-payload', 600);
        $product->update(['is_in_stock' => false]);
        $this->assertNull(Cache::get('sections.homepage'), 'stock change must invalidate homepage cache');

        Cache::put('sections.homepage', 'cached-payload', 600);
        $product->update(['price' => '123.45']);
        $this->assertSame('cached-payload', Cache::get('sections.homepage'), 'unrelated change must NOT invalidate');

        Cache::put('sections.homepage', 'cached-payload', 600);
        $product->update(['is_active' => false]);
        $this->assertNull(Cache::get('sections.homepage'), 'visibility change must invalidate homepage cache');
    }

    /**
     * Regression: ConditionResource's name-field afterStateUpdated() type-hinted
     * the pre-Filament-5 Forms\Get/Forms\Set classes instead of the real
     * Schemas\Components\Utilities\Get/Set that Livewire actually injects —
     * a TypeError on every keystroke, confirmed live, broke auto-slug-from-name
     * on Condition create (the only resource in the codebase using this
     * stale v3-era type-hint).
     */
    public function test_condition_name_field_auto_slug_does_not_throw(): void
    {
        Livewire::test(\App\Filament\Resources\ConditionResource\Pages\CreateCondition::class)
            ->fillForm(['name' => 'Refurbished'])
            ->assertHasNoErrors();
    }

    /**
     * Regression: Manufacturer's country_code form field was ->nullable()
     * but the migration column has no ->nullable() (NOT NULL) — submitting
     * without a country threw a raw SQLSTATE constraint-violation 500
     * instead of a friendly validation message, confirmed live.
     */
    public function test_manufacturer_create_without_country_shows_validation_error_not_a_500(): void
    {
        Livewire::test(\App\Filament\Resources\ManufacturerResource\Pages\CreateManufacturer::class)
            ->fillForm(['name' => ['en' => 'Test Brand'], 'slug' => 'test-brand'])
            ->call('create')
            ->assertHasFormErrors(['country_code' => 'required']);
    }
}
