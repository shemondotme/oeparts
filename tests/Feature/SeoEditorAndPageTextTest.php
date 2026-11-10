<?php

namespace Tests\Feature;

use App\Filament\Support\SeoFormFields;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\LocalizedText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Brand and car-model pages can carry their own SEO title, description and
 * introduction (per locale), with SEO-settings templates as the middle layer
 * and the built-in wording as the last — and never an English fallback on
 * another language's page. Plus the editor's length-meter thresholds.
 */
class SeoEditorAndPageTextTest extends TestCase
{
    use RefreshDatabase;

    private function brand(array $attrs = []): Manufacturer
    {
        return Manufacturer::create($attrs + [
            'name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true,
        ]);
    }

    private function model(Manufacturer $brand, array $attrs = []): CarModel
    {
        return CarModel::create($attrs + [
            'manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket',
            'year_from' => 2015, 'year_to' => 2022, 'is_active' => true,
        ]);
    }

    private function setting(string $key, array|string $value): void
    {
        Setting::updateOrCreate(
            ['group' => 'seo', 'key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value, 'type' => 'json', 'is_encrypted' => false]
        );
        app(SettingsService::class)->forget('seo');
    }

    private function title(string $html): string
    {
        preg_match('#<title>(.*?)</title>#s', $html, $m);

        return html_entity_decode(trim($m[1] ?? ''));
    }

    private function description(string $html): string
    {
        preg_match('#<meta name="description" content="([^"]*)">#', $html, $m);

        return html_entity_decode($m[1] ?? '');
    }

    // ── Brand page ────────────────────────────────────────────────────────

    #[Test]
    public function a_brands_own_title_and_description_win_in_their_own_locale(): void
    {
        $this->brand(['meta_title' => ['en' => 'Acme parts, direct'], 'meta_description' => ['en' => 'Hand-written brand description.']]);

        $html = $this->get('/en/brand/acme')->getContent();

        $this->assertSame('Acme parts, direct', $this->title($html));
        $this->assertSame('Hand-written brand description.', $this->description($html));
    }

    #[Test]
    public function another_locale_never_shows_the_english_text_it_falls_back_to_the_template(): void
    {
        $this->brand(['meta_title' => ['en' => 'Acme parts, direct'], 'intro' => ['en' => 'English only intro.']]);

        $html = $this->get('/de/brand/acme')->getContent();

        $this->assertStringNotContainsString('Acme parts, direct', $this->title($html));
        $this->assertStringNotContainsString('English only intro.', $html);
        $this->assertStringNotContainsString('data-testid="brand-intro"', $html);
    }

    #[Test]
    public function the_brand_intro_is_shown_as_paragraphs_and_cannot_inject_markup(): void
    {
        $this->brand(['intro' => ['en' => "First paragraph.\n\nSecond <script>alert(1)</script> paragraph."]]);

        $html = $this->get('/en/brand/acme')->getContent();

        $this->assertStringContainsString('data-testid="brand-intro"', $html);
        $this->assertStringContainsString('<p>First paragraph.</p>', $html);
        $this->assertStringContainsString('Second &lt;script&gt;alert(1)&lt;/script&gt; paragraph.', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    #[Test]
    public function the_brand_description_template_applies_when_the_brand_has_no_text_of_its_own(): void
    {
        $this->setting('brand_description_template', ['en' => '{brand} OEM parts: {count} numbers on {site}.']);
        $this->brand();

        $this->assertMatchesRegularExpression('/^Acme OEM parts: 0 numbers on .+\.$/', $this->description($this->get('/en/brand/acme')->getContent()));
    }

    #[Test]
    public function a_brand_with_no_custom_text_renders_exactly_as_before(): void
    {
        $this->brand();

        $html = $this->get('/en/brand/acme')->getContent();

        $this->assertStringContainsString('Acme', $this->title($html));
        $this->assertStringNotContainsString('data-testid="brand-intro"', $html);
    }

    // ── Car-model page ────────────────────────────────────────────────────

    #[Test]
    public function a_car_models_own_text_wins_then_the_template_then_the_builtin_wording(): void
    {
        $brand = $this->brand();
        $this->model($brand);
        $builtIn = $this->title($this->get('/en/brand/acme/rocket')->getContent());
        $this->assertStringContainsString('Rocket', $builtIn);

        $this->setting('model_title_template', ['en' => '{brand} {model} parts ({years})']);
        $this->assertSame('Acme Rocket parts (2015–2022)', $this->title($this->get('/en/brand/acme/rocket')->getContent()));

        CarModel::where('slug', 'rocket')->update(['meta_title' => json_encode(['en' => 'Rocket — own title'])]);
        $this->assertSame('Rocket — own title', $this->title($this->get('/en/brand/acme/rocket')->getContent()));
    }

    #[Test]
    public function a_car_model_intro_replaces_the_generic_line_in_its_own_locale_only(): void
    {
        $this->model($this->brand(), ['intro' => ['de' => 'Deutscher Einleitungstext.']]);

        $this->assertStringContainsString('Deutscher Einleitungstext.', $this->get('/de/brand/acme/rocket')->getContent());
        $this->assertStringNotContainsString('Deutscher Einleitungstext.', $this->get('/en/brand/acme/rocket')->getContent());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    #[Test]
    public function localized_text_is_locale_strict_and_paragraphs_are_escaped(): void
    {
        $field = ['en' => ' Hello ', 'de' => ''];

        $this->assertSame('Hello', LocalizedText::exact($field, 'en'));
        $this->assertSame('', LocalizedText::exact($field, 'de'), 'no English fallback');
        $this->assertSame('', LocalizedText::exact($field, 'fr'));
        $this->assertSame('', LocalizedText::exact(null, 'en'));
        $this->assertSame(['a &amp; b', "c<br>\nd"], LocalizedText::paragraphs("a & b\n\nc\nd"));
        $this->assertSame([], LocalizedText::paragraphs("  \n\n "));
    }

    #[Test]
    public function the_length_meter_thresholds_match_what_google_shows(): void
    {
        $title = fn (int $n) => SeoFormFields::status($n, SeoFormFields::TITLE_MIN, SeoFormFields::TITLE_MAX);

        $this->assertSame('muted', $title(0)['color']);
        $this->assertSame('warn', $title(29)['color']);
        $this->assertSame('good', $title(30)['color']);
        $this->assertSame('good', $title(60)['color']);
        $this->assertSame('bad', $title(61)['color']);

        $desc = fn (int $n) => SeoFormFields::status($n, SeoFormFields::DESC_MIN, SeoFormFields::DESC_MAX);
        $this->assertSame('warn', $desc(69)['color']);
        $this->assertSame('good', $desc(160)['color']);
        $this->assertSame('bad', $desc(161)['color']);
    }

    #[Test]
    public function the_serp_preview_renders_the_truncated_snippet_and_the_meters(): void
    {
        $html = view('filament.components.serp-preview', [
            'title' => str_repeat('T', 80),
            'description' => 'A short description.',
            'url' => 'https://example.org/en/brand/acme',
            'titleStatus' => SeoFormFields::status(80, 30, 60),
            'descStatus' => SeoFormFields::status(0, 70, 160),
        ])->render();

        $this->assertStringContainsString('example.org › en › brand › acme', $html);
        $this->assertStringContainsString(str_repeat('T', 59).'…', $html, 'cut like Google cuts it');
        $this->assertStringContainsString('Title: 80 / 60', $html);
        $this->assertStringContainsString('too long', $html);
        $this->assertStringContainsString('using the automatic text', $html);
    }
}
