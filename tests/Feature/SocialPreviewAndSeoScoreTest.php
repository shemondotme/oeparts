<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\BlogPostResource\Pages\EditBlogPost;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Filament\Resources\CarModelResource\Pages\EditCarModel;
use App\Filament\Resources\ManufacturerResource\Pages\EditManufacturer;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Support\SeoFormFields;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use App\Models\Page;
use App\Services\ContentAnalyzer;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The share-card preview in Advanced SEO and the SEO score column in the Pages / Blog lists. */
class SocialPreviewAndSeoScoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function page(array $attrs = []): Page
    {
        return Page::create($attrs + [
            'title' => ['en' => 'Brake pads guide'], 'slug' => 'brake-pads-guide',
            'content' => ['en' => '<p>Choosing brake pads is easy.</p>'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    // ── Social preview ────────────────────────────────────────────────────

    public function test_the_share_preview_is_in_the_advanced_seo_section_of_all_four_editors(): void
    {
        $page = $this->page();
        $post = BlogPost::create(['title' => ['en' => 'T'], 'slug' => 't', 'content' => ['en' => 'x'], 'status' => ContentStatus::Draft, 'author_id' => Admin::factory()->create()->id]);
        $brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        $model = CarModel::create(['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'is_active' => true]);

        Livewire::test(EditPage::class, ['record' => $page->id])->assertOk()->assertSee('Share preview');
        Livewire::test(EditBlogPost::class, ['record' => $post->id])->assertOk()->assertSee('Share preview');
        Livewire::test(EditManufacturer::class, ['record' => $brand->id])->assertOk()->assertSee('Share preview');
        Livewire::test(EditCarModel::class, ['record' => $model->id])->assertOk()->assertSee('Share preview');
    }

    public function test_the_share_card_uses_the_social_text_and_image_when_set_and_the_automatic_text_otherwise(): void
    {
        $page = $this->page();
        $image = MediaFile::create(['file_name' => 'share.png', 'file_path' => 'media/share.png', 'file_url' => 'https://cdn.example.org/share.png', 'mime_type' => 'image/png', 'size' => 10, 'uploaded_by' => Admin::factory()->create()->id]);

        $component = Livewire::test(EditPage::class, ['record' => $page->id]);
        $component->assertSeeHtml('<div class="oc-social-title">Brake pads guide')   // the automatic title, inside the card itself
            ->assertSee('The site-wide default image is shared');

        $component->fillForm([
            'seoMeta.og_title' => 'A better title to share',
            'seoMeta.og_description' => 'Written for social feeds.',
            'seoMeta.og_image_id' => $image->id,
        ])->assertSee('A better title to share')
            ->assertSee('Written for social feeds.')
            ->assertSee($image->file_url, false);
    }

    // ── SEO score ─────────────────────────────────────────────────────────

    public function test_the_list_score_judges_content_alone_when_no_keyword_is_set(): void
    {
        $thin = $this->page(['content' => ['en' => '<p>Hi.</p>']]);
        $good = $this->page(['slug' => 'good', 'content' => ['en' => '<p>'.str_repeat('A useful sentence about the part and how it fits your car. ', 40).'</p><h2>Fitting</h2><p>See <a href="/en/brands">brands</a>.</p>'], 'meta_title' => ['en' => 'A well sized search title for the page'], 'meta_description' => ['en' => str_repeat('d', 100)]]);

        $this->assertLessThan(SeoFormFields::listScore($good)['score'], SeoFormFields::listScore($thin)['score']);
        $this->assertGreaterThanOrEqual(75, SeoFormFields::listScore($good)['score'], 'a solid page is not marked down just for having no keyword');
    }

    public function test_requiring_the_keyword_lowers_the_score_of_the_same_text(): void
    {
        $analyzer = app(ContentAnalyzer::class);
        $args = [null, 'A well sized search title for the page', str_repeat('d', 100), 'a-slug', '<p>'.str_repeat('Some words in a sentence. ', 80).'</p>'];

        $lenient = $analyzer->analyze(...$args, title: null, requireKeyword: false);
        $strict = $analyzer->analyze(...$args, title: null, requireKeyword: true);

        $this->assertGreaterThan($strict['score'], $lenient['score']);
    }

    public function test_the_pages_and_blog_lists_show_an_seo_column(): void
    {
        $this->page();
        BlogPost::create(['title' => ['en' => 'T'], 'slug' => 't', 'content' => ['en' => '<p>x</p>'], 'status' => ContentStatus::Draft, 'author_id' => Admin::factory()->create()->id]);

        Livewire::test(ListPages::class)->assertOk()->assertTableColumnExists('seo_score');
        Livewire::test(ListBlogPosts::class)->assertOk()->assertTableColumnExists('seo_score');
    }

    public function test_a_record_without_content_never_breaks_the_list(): void
    {
        $empty = Page::create([
            'title' => ['en' => 'Empty'], 'slug' => 'empty', 'content' => [], 'status' => ContentStatus::Draft,
            'is_homepage' => false, 'created_by' => Admin::factory()->create()->id,
        ]);

        $this->assertIsInt(SeoFormFields::listScore($empty)['score']);
        Livewire::test(ListPages::class)->assertOk();
    }

    public function test_the_share_card_prefers_the_pages_own_search_title_and_description_over_the_automatic_text(): void
    {
        $page = $this->page(['meta_title' => ['en' => 'Own search headline'], 'meta_description' => ['en' => 'Own search summary.']]);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->assertSeeHtml('<div class="oc-social-title">Own search headline')
            ->assertSeeHtml('<div class="oc-social-desc">Own search summary.');
    }

    public function test_the_share_card_can_be_previewed_in_another_language(): void
    {
        $page = $this->page(['meta_title' => ['en' => 'English headline', 'de' => 'Deutsche Überschrift'], 'meta_description' => ['en' => 'English summary.', 'de' => 'Deutsche Zusammenfassung.']]);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->assertSeeHtml('<div class="oc-social-title">English headline')
            ->fillForm(['seoMeta.social_preview_locale' => 'de'])
            ->assertSeeHtml('<div class="oc-social-title">Deutsche Überschrift')
            ->assertSeeHtml('<div class="oc-social-desc">Deutsche Zusammenfassung.');
    }

    public function test_the_list_score_follows_the_default_language(): void
    {
        \App\Models\Language::query()->update(['is_default' => false]);
        \App\Models\Language::where('code', 'de')->update(['is_default' => true]);
        \App\Support\LocaleRegistry::forget();

        $page = $this->page(['content' => ['en' => '<p>Hi.</p>', 'de' => '<p>'.str_repeat('Ein nützlicher Satz über das Teil und wie es passt. ', 40).'</p><h2>Einbau</h2>'], 'meta_title' => ['de' => 'Ein gut bemessener Suchtitel für die Seite'], 'meta_description' => ['de' => str_repeat('d', 100)]]);

        $this->assertGreaterThanOrEqual(60, SeoFormFields::listScore($page)['score'], 'scored on the German text, not the thin English one');
    }
}
