<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\Admin;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Placeholder tokens ({oem}, {min}, {max}, {manufacturer}, {brand}, {site})
 * were documented only in helper text, with no way to see how a template
 * actually renders short of saving it and checking the live storefront page —
 * a real risk given one of these templates affects every OEM search page at
 * once. Live-updating previews (against one real sample product/brand) now
 * render as the admin types.
 */
class SeoControlCenterTemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function search_results_title_template_preview_updates_live_against_a_real_sample_product(): void
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $condition->id, 'price' => '129.99', 'is_active' => true, 'is_in_stock' => true,
        ]);

        Livewire::test(SeoControlCenter::class)
            ->set('data.search_results_title_template.en', 'Buy OEM Part {oem} — From €{min}')
            ->assertSee('Buy OEM Part 06L906036L — From €129.99');
    }

    #[Test]
    public function brand_title_template_preview_uses_a_sample_manufacturer_name(): void
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => 'Continental'], 'slug' => 'continental', 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'ABC123', 'normalized_oem' => 'ABC123',
            'condition_id' => $condition->id, 'price' => '50.00', 'is_active' => true, 'is_in_stock' => true,
        ]);

        Livewire::test(SeoControlCenter::class)
            ->set('data.brand_title_template.en', 'Genuine {brand} OEM Parts')
            ->assertSee('Genuine Continental OEM Parts');
    }

    #[Test]
    public function preview_falls_back_to_illustrative_dummy_values_on_an_empty_catalog(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->set('data.search_results_title_template.en', 'Buy OEM Part {oem}')
            ->assertSee('Buy OEM Part ABC12345');
    }

    #[Test]
    public function preview_shows_a_placeholder_message_when_the_template_field_is_empty(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->set('data.console_title_template.en', '')
            ->assertSee('nothing typed yet');
    }
}
