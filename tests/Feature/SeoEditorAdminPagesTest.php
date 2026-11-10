<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Filament\Resources\CarModelResource\Pages\EditCarModel;
use App\Filament\Resources\ManufacturerResource\Pages\EditManufacturer;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Admin;
use App\Models\CarModel;
use App\Models\Manufacturer;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shared SEO editor block (per-locale fields + live Google-style
 * preview + meters) mounts on every edit form that uses it, and brand /
 * car-model SEO text actually persists.
 */
class SeoEditorAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    public function test_the_seo_editor_block_mounts_on_page_blog_brand_and_model_forms(): void
    {
        $brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        $model = CarModel::create(['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'is_active' => true]);

        Livewire::test(CreatePage::class)->assertOk()->assertSee('Search result preview');
        Livewire::test(CreateBlogPost::class)->assertOk()->assertSee('Search result preview');
        Livewire::test(EditManufacturer::class, ['record' => $brand->id])->assertOk()->assertSee('Search result preview')->assertSee('Brand page text');
        Livewire::test(EditCarModel::class, ['record' => $model->id])->assertOk()->assertSee('Search result preview')->assertSee('Model page text');
    }

    public function test_brand_and_model_seo_text_is_saved(): void
    {
        $brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        $model = CarModel::create(['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'is_active' => true]);

        Livewire::test(EditManufacturer::class, ['record' => $brand->id])
            ->fillForm([
                'meta_title.en' => 'Acme parts — own title',
                'meta_description.de' => 'Eigene Beschreibung.',
                'intro.en' => 'Intro text.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $brand->refresh();
        $this->assertSame('Acme parts — own title', $brand->meta_title['en']);
        $this->assertSame('Eigene Beschreibung.', $brand->meta_description['de']);
        $this->assertSame('Intro text.', $brand->intro['en']);

        Livewire::test(EditCarModel::class, ['record' => $model->id])
            ->fillForm(['meta_title.en' => 'Rocket — own title', 'intro.fr' => 'Texte.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $model->refresh();
        $this->assertSame('Rocket — own title', $model->meta_title['en']);
        $this->assertSame('Texte.', $model->intro['fr']);
    }
}
