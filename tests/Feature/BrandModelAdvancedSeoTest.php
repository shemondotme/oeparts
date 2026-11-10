<?php

namespace Tests\Feature;

use App\Filament\Resources\CarModelResource\Pages\EditCarModel;
use App\Filament\Resources\ManufacturerResource\Pages\EditManufacturer;
use App\Models\Admin;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\SeoMeta;
use App\Services\SitemapService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Brand and car-model pages: their own robots directive, canonical URL and
 * social text/image (Advanced SEO), the vehicle schema, and the sitemap
 * leaving out whatever was set to noindex.
 */
class BrandModelAdvancedSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []); // parallel runs share this real folder
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function brand(array $attrs = []): Manufacturer
    {
        return Manufacturer::create($attrs + ['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
    }

    private function model(Manufacturer $brand, array $attrs = []): CarModel
    {
        return CarModel::create($attrs + ['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'year_from' => 2015, 'year_to' => 2022, 'is_active' => true]);
    }

    private function seoFor(object $entity, array $attrs): SeoMeta
    {
        return SeoMeta::create($attrs + ['metable_type' => get_class($entity), 'metable_id' => $entity->id, 'robots' => 'index,follow']);
    }

    // ── Brand page ────────────────────────────────────────────────────────

    #[Test]
    public function a_brand_without_an_override_renders_exactly_as_before(): void
    {
        $this->brand();

        $html = $this->get('/en/brand/acme')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/en/brand/acme').'">', $html);
        $this->assertStringNotContainsString('content="noindex', $html);
    }

    #[Test]
    public function a_brand_can_be_set_to_noindex_and_given_its_own_canonical_and_social_text(): void
    {
        $brand = $this->brand();
        $this->seoFor($brand, ['robots' => 'noindex,follow', 'canonical_url' => 'https://example.org/preferred-acme', 'og_title' => 'Share title', 'og_description' => 'Share description']);

        $html = $this->get('/en/brand/acme')->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.org/preferred-acme">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Share title">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Share description">', $html);
    }

    #[Test]
    public function a_brand_shares_its_logo_as_the_social_image_when_it_has_one(): void
    {
        $logo = \App\Models\MediaFile::create(['file_name' => 'acme.png', 'file_path' => 'media/acme.png', 'file_url' => 'https://cdn.example.org/media/acme.png', 'mime_type' => 'image/png', 'size' => 100, 'uploaded_by' => Admin::factory()->create()->id]);
        $this->brand(['logo_id' => $logo->id]);

        $html = $this->get('/en/brand/acme')->getContent();

        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]*acme\.png">#', $html);
    }

    #[Test]
    public function the_brand_organization_schema_has_a_stable_id_and_omits_a_missing_logo(): void
    {
        $this->brand();

        $html = $this->get('/en/brand/acme')->getContent();
        preg_match('#<script type="application/ld\+json">\s*(\{[^<]*"@type":\s*"Organization"[^<]*\})\s*</script>#s', $html, $m);
        $org = json_decode(html_entity_decode($m[1] ?? '{}'), true);

        $this->assertSame('Organization', $org['@type']);
        $this->assertStringEndsWith('/en/brand/acme#brand', $org['@id']);
        $this->assertArrayNotHasKey('logo', $org);
    }

    // ── Car-model page ────────────────────────────────────────────────────

    #[Test]
    public function a_model_can_be_set_to_noindex_and_given_its_own_canonical(): void
    {
        $model = $this->model($this->brand());
        $this->seoFor($model, ['robots' => 'noindex,nofollow', 'canonical_url' => 'https://example.org/preferred-rocket']);

        $html = $this->get('/en/brand/acme/rocket')->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.org/preferred-rocket">', $html);
    }

    #[Test]
    public function the_model_page_declares_the_vehicle_it_is_about(): void
    {
        $this->model($this->brand());

        $html = $this->get('/en/brand/acme/rocket')->getContent();
        preg_match('#<script type="application/ld\+json">\s*(\{[^<]*"@type":\s*"Car"[^<]*\})\s*</script>#s', $html, $m);
        $car = json_decode(html_entity_decode($m[1] ?? '{}'), true);

        $this->assertSame('Car', $car['@type']);
        $this->assertSame('Acme Rocket', $car['name']);
        $this->assertSame('Rocket', $car['model']);
        $this->assertSame('Acme', $car['brand']['name']);
        $this->assertSame('2015', $car['vehicleModelDate']);
        $this->assertStringEndsWith('/en/brand/acme/rocket#vehicle', $car['@id']);
    }

    #[Test]
    public function a_model_without_a_start_year_omits_the_date_fields_instead_of_inventing_them(): void
    {
        $this->model($this->brand(), ['year_from' => null, 'year_to' => null]);

        $html = $this->get('/en/brand/acme/rocket')->getContent();
        preg_match('#<script type="application/ld\+json">\s*(\{[^<]*"@type":\s*"Car"[^<]*\})\s*</script>#s', $html, $m);
        $car = json_decode(html_entity_decode($m[1] ?? '{}'), true);

        $this->assertArrayNotHasKey('vehicleModelDate', $car);
        $this->assertArrayNotHasKey('productionDate', $car);
    }

    // ── Sitemap ───────────────────────────────────────────────────────────

    #[Test]
    public function noindex_brands_and_models_are_left_out_of_the_sitemap(): void
    {
        $visible = $this->brand(['slug' => 'visible', 'name' => ['en' => 'Visible']]);
        $hidden = $this->brand(['slug' => 'hidden', 'name' => ['en' => 'Hidden']]);
        $this->model($visible, ['slug' => 'shown-model']);
        $hiddenModel = $this->model($visible, ['slug' => 'hidden-model']);
        $this->seoFor($hidden, ['robots' => 'noindex,follow']);
        $this->seoFor($hiddenModel, ['robots' => 'noindex,follow']);

        app(SitemapService::class)->generateAll();
        $brands = file_get_contents(public_path('sitemaps/sitemap-brands.xml'));
        $models = file_get_contents(public_path('sitemaps/sitemap-models.xml'));

        $this->assertStringContainsString('/en/brand/visible</loc>', $brands);
        $this->assertStringNotContainsString('/brand/hidden', $brands);
        $this->assertStringContainsString('/brand/visible/shown-model</loc>', $models);
        $this->assertStringNotContainsString('hidden-model', $models);
    }

    // ── The admin form ────────────────────────────────────────────────────

    public function test_the_advanced_seo_section_saves_for_a_brand_and_a_model(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
        $brand = $this->brand();
        $model = $this->model($brand);

        Livewire::test(EditManufacturer::class, ['record' => $brand->id])
            ->assertSee('Advanced SEO')
            ->fillForm(['seoMeta.robots' => 'noindex,follow', 'seoMeta.canonical_url' => 'https://example.org/acme'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(EditCarModel::class, ['record' => $model->id])
            ->assertSee('Advanced SEO')
            ->fillForm(['seoMeta.robots' => 'noindex,nofollow'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('noindex,follow', $brand->fresh()->seoMeta->robots);
        $this->assertSame('https://example.org/acme', $brand->fresh()->seoMeta->canonical_url);
        $this->assertSame('noindex,nofollow', $model->fresh()->seoMeta->robots);
    }

    public function test_saving_a_brand_without_touching_advanced_seo_does_not_break_or_noindex_it(): void
    {
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
        $brand = $this->brand();

        Livewire::test(EditManufacturer::class, ['record' => $brand->id])->call('save')->assertHasNoFormErrors();

        $this->assertNotContains('noindex', [$brand->fresh()->seoMeta?->robots ?? 'index,follow']);
        $this->assertStringNotContainsString('content="noindex', $this->get('/en/brand/acme')->getContent());
    }
}
