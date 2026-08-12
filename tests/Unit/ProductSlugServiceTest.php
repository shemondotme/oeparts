<?php

namespace Tests\Unit;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\ProductSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductSlugServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductSlugService $service;
    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductSlugService::class);

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch', 'de' => 'Bosch', 'lt' => 'Bosch', 'fr' => 'Bosch', 'es' => 'Bosch'],
            'slug' => 'bosch',
            'country_code' => 'DE',
            'is_active' => true,
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L',
            'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id,
            'price' => '100.00',
            'is_in_stock' => true,
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function ascii_name_produces_a_clean_lowercase_hyphenated_slug(): void
    {
        $product = $this->makeProduct(['name' => ['en' => 'Brake Pad Front']]);

        $slug = $this->service->generate($product, 'en');

        $this->assertSame('bosch-brake-pad-front-new', $slug);
    }

    #[Test]
    public function cyrillic_name_is_transliterated_to_ascii(): void
    {
        // Str::slug() ASCII-transliterates — the "Latin/ASCII slug in every
        // locale" requirement, not native-script URLs.
        $product = $this->makeProduct(['name' => ['en' => 'Тормозная колодка']]);

        $slug = $this->service->generate($product, 'en');

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
        $this->assertStringNotContainsString('т', $slug);
    }

    #[Test]
    public function japanese_name_still_produces_a_pure_ascii_slug(): void
    {
        // CJK transliteration support is limited — the point here isn't a
        // meaningful romanization, just that the result is NEVER raw
        // non-Latin characters (and never empty, thanks to the OEM
        // fallback) in a URL segment.
        $product = $this->makeProduct(['name' => ['en' => 'ブレーキパッド']]);

        $slug = $this->service->generate($product, 'en');

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
        $this->assertNotSame('', $slug);
    }

    #[Test]
    public function no_name_manufacturer_or_condition_falls_back_to_the_oem_number(): void
    {
        // Constructed in-memory (never saved) so condition_id/manufacturer_id
        // can genuinely both be null without tripping DB-level constraints —
        // this is purely testing ProductSlugService's own fallback logic.
        $product = new Product([
            'oem_number' => '1K0615301AA',
            'name' => ['en' => ''],
        ]);

        $slug = $this->service->generate($product, 'en');

        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $slug);
        $this->assertStringContainsString('1k0615301aa', $slug);
    }

    #[Test]
    public function build_id_slug_combines_id_and_slug_with_a_hyphen(): void
    {
        $product = $this->makeProduct(['name' => ['en' => 'Brake Pad Front']]);

        $idSlug = $this->service->buildIdSlug($product, 'en');

        $this->assertSame("{$product->id}-bosch-brake-pad-front-new", $idSlug);
    }

    #[Test]
    public function falls_back_to_english_name_when_requested_locale_has_no_translation(): void
    {
        // ProductFactory's real-world default only ever populates en/de —
        // trans_field() falls back to English for lt/fr/es. The condition
        // label is still genuinely localized ("Nauja", not "New") since
        // Condition rows have real per-locale lang-file translations —
        // this is the "computed per-locale" behavior working correctly,
        // not a second fallback.
        $product = $this->makeProduct(['name' => ['en' => 'Brake Pad Front', 'de' => 'Bremsbelag vorne']]);

        $ltSlug = $this->service->generate($product, 'lt');

        $this->assertSame('bosch-brake-pad-front-nauja', $ltSlug);
    }
}
