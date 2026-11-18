<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Jobs\RegenerateSitemapGroups;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\SitemapService;
use App\Support\PublicBaseUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Languages in Search": the languages the admin ticks may be indexed; the others stay usable but are noindex,
 * out of the sitemap and not advertised through hreflang. Plus the live update of the small sitemap files.
 */
class LanguagesInSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, string> $codes */
    private function index(array $codes): void
    {
        app(SettingsService::class)->set('seo.indexed_locales', json_encode($codes));
    }

    private function makePage(): Page
    {
        return Page::create([
            'title' => ['en' => 'Policy', 'de' => 'Richtlinie'], 'slug' => 'policy-'.uniqid(),
            'content' => ['en' => '<p>Text of the policy.</p>', 'de' => '<p>Text der Richtlinie.</p>'],
            'status' => 'published', 'published_at' => now()->subDay(), 'created_by' => Admin::factory()->create()->id,
        ]);
    }

    private function brand(string $slug = 'audi'): Manufacturer
    {
        $manufacturer = Manufacturer::create(['name' => ['en' => ucfirst($slug)], 'slug' => $slug, 'country_code' => 'DE', 'is_active' => true]);
        $condition = Condition::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]);
        Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'LOC'.$manufacturer->id, 'normalized_oem' => 'LOC'.$manufacturer->id,
            'name' => ['en' => 'Part'], 'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);

        return $manufacturer;
    }

    #[Test]
    public function by_default_every_language_may_be_indexed(): void
    {
        $this->assertFalse(SeoService::restrictsIndexedLocales());
        $this->assertFalse(SeoService::isNonIndexedLocale('de'));
    }

    #[Test]
    public function languages_outside_the_list_are_noindex_and_the_listed_ones_are_not(): void
    {
        $page = $this->makePage();
        $this->index(['en']);

        $en = $this->get('/en/'.$page->slug)->getContent();
        $de = $this->get('/de/'.$page->slug)->getContent();

        $this->assertDoesNotMatchRegularExpression('#<meta name="robots" content="[^"]*noindex#', $en);
        $this->assertMatchesRegularExpression('#<meta name="robots" content="noindex,follow"#', $de);
    }

    #[Test]
    public function one_indexed_language_means_no_hreflang_at_all(): void
    {
        $page = $this->makePage();

        $this->assertStringContainsString('hreflang=', $this->get('/en/'.$page->slug)->getContent());

        $this->index(['en']);
        $this->assertStringNotContainsString('hreflang=', $this->get('/en/'.$page->slug)->getContent());
    }

    #[Test]
    public function hreflang_only_lists_the_indexed_languages(): void
    {
        $page = $this->makePage();
        $this->index(['en', 'de']);

        $html = $this->get('/en/'.$page->slug)->getContent();

        $this->assertStringContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="de"', $html);
        $this->assertStringNotContainsString('hreflang="fr"', $html);
        $this->assertStringNotContainsString('hreflang="lt"', $html);
    }

    #[Test]
    public function the_sitemap_lists_only_the_chosen_languages(): void
    {
        $this->brand();
        Http::fake();

        app(SitemapService::class)->generateAll();
        $this->assertStringContainsString('/de/brand/audi', file_get_contents(public_path('sitemaps/sitemap-brands.xml')));

        $this->index(['en']);
        app(SitemapService::class)->generateAll();
        $one = file_get_contents(public_path('sitemaps/sitemap-brands.xml'));
        $this->assertStringContainsString('/en/brand/audi', $one);
        $this->assertStringNotContainsString('/de/brand/audi', $one);
        $this->assertStringNotContainsString('hreflang', $one);
    }

    #[Test]
    public function a_partial_rebuild_touches_only_the_named_groups_and_keeps_the_rest_in_the_index(): void
    {
        $this->brand();
        Http::fake();
        app(SitemapService::class)->generateAll();

        $partsBefore = file_get_contents(public_path('sitemaps/sitemap-parts.xml'));
        file_put_contents(public_path('sitemaps/sitemap-parts.xml'), $partsBefore.'<!-- untouched marker -->');

        $this->brand('bmw');
        app(SitemapService::class)->generateAll(['brands']);

        $this->assertStringContainsString('untouched marker', file_get_contents(public_path('sitemaps/sitemap-parts.xml')));
        $this->assertStringContainsString('/brand/bmw', file_get_contents(public_path('sitemaps/sitemap-brands.xml')));

        $index = file_get_contents(public_path('sitemap.xml'));
        foreach (['sitemap-parts.xml', 'sitemap-brands.xml', 'sitemap-pages.xml', 'sitemap-blog.xml'] as $file) {
            $this->assertStringContainsString($file, $index);
        }
    }

    #[Test]
    public function saving_a_page_queues_a_rebuild_of_the_pages_sitemap_when_live_update_is_on(): void
    {
        config(['seo.live_sitemap_in_tests' => true]);
        Http::fake();
        app(SitemapService::class)->generateAll();
        Bus::fake();

        app(SettingsService::class)->set('seo.sitemap_live_update', '1');
        $this->makePage();

        Bus::assertDispatched(RegenerateSitemapGroups::class, fn ($job) => $job->groups === ['pages']);
    }

    #[Test]
    public function nothing_is_queued_when_live_update_is_off(): void
    {
        config(['seo.live_sitemap_in_tests' => true]);
        Http::fake();
        app(SitemapService::class)->generateAll();
        Bus::fake();
        app(SettingsService::class)->set('seo.sitemap_live_update', '0');
        $this->makePage();
        Bus::assertNotDispatched(RegenerateSitemapGroups::class);
    }

    #[Test]
    public function a_site_without_car_models_publishes_no_empty_models_sitemap(): void
    {
        Http::fake();
        file_put_contents(public_path('sitemaps/sitemap-models.xml'), '<urlset/>');

        app(SitemapService::class)->generateAll();

        $this->assertFileDoesNotExist(public_path('sitemaps/sitemap-models.xml'));
        $this->assertStringNotContainsString('sitemap-models', file_get_contents(public_path('sitemap.xml')));
    }

    #[Test]
    public function an_http_site_address_is_upgraded_to_https_when_the_app_is_served_over_https(): void
    {
        config(['app.url' => 'https://oeparts.lt']);
        app(SettingsService::class)->set('general.site_url', 'http://oeparts.lt');

        $this->assertSame('https://oeparts.lt', PublicBaseUrl::resolve(app(SettingsService::class)));
    }

    #[Test]
    public function the_recent_products_sitemap_lists_only_oem_numbers_with_a_product_added_lately(): void
    {
        $m = $this->brand('bmw');
        DB::table('products')->where('manufacturer_id', $m->id)->update(['created_at' => now()->subDays(40)]);
        $condition = Condition::where('slug', 'new')->first();
        Product::create([
            'manufacturer_id' => $m->id, 'oem_number' => 'FRESH123', 'normalized_oem' => 'FRESH123',
            'name' => ['en' => 'New part'], 'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        Http::fake();

        app(SitemapService::class)->generateAll();

        $recent = file_get_contents(public_path('sitemaps/sitemap-recent.xml'));
        $this->assertStringContainsString('FRESH123', $recent);
        $this->assertStringNotContainsString('LOC'.$m->id, $recent);
        $this->assertStringContainsString('sitemap-recent.xml', file_get_contents(public_path('sitemap.xml')));
    }

    #[Test]
    public function with_nothing_recent_there_is_no_recent_file(): void
    {
        $m = $this->brand('bmw');
        DB::table('products')->where('manufacturer_id', $m->id)->update(['created_at' => now()->subDays(40)]);
        file_put_contents(public_path('sitemaps/sitemap-recent.xml'), '<urlset/>');
        Http::fake();

        app(SitemapService::class)->generateAll();

        $this->assertFileDoesNotExist(public_path('sitemaps/sitemap-recent.xml'));
    }


    #[Test]
    public function only_public_content_reaches_the_sitemap_never_drafts_scheduled_or_inactive_items(): void
    {
        $author = Admin::factory()->create()->id;
        $mk = fn (string $slug, string $status, $publishedAt) => Page::create([
            'title' => ['en' => $slug], 'slug' => $slug, 'content' => ['en' => '<p>Body text.</p>'],
            'status' => $status, 'published_at' => $publishedAt, 'created_by' => $author,
        ]);
        $mk('live-page', 'published', now()->subDay());
        $mk('draft-page', 'draft', null);
        $mk('scheduled-page', 'published', now()->addDay());

        $post = fn (string $slug, $status, $publishedAt) => BlogPost::create([
            'title' => ['en' => $slug], 'slug' => $slug, 'content' => ['en' => '<p>Body text.</p>'],
            'status' => $status, 'published_at' => $publishedAt, 'author_id' => $author,
        ]);
        $post('live-post', ContentStatus::Published, now()->subDay());
        $post('draft-post', ContentStatus::Draft, null);
        $post('scheduled-post', ContentStatus::Published, now()->addDay());

        $m = $this->brand('bmw');
        $condition = Condition::where('slug', 'new')->first();
        $part = fn (string $oem, array $extra) => Product::create($extra + [
            'manufacturer_id' => $m->id, 'oem_number' => $oem, 'normalized_oem' => $oem, 'name' => ['en' => 'Part'],
            'price' => 10, 'condition_id' => $condition->id, 'is_active' => true, 'is_in_stock' => true,
        ]);
        $part('PUBLICOEM', []);
        $part('HIDDENOEM', ['is_active' => false]);
        $part('DELETEDOEM', [])->delete();
        Http::fake();

        app(SitemapService::class)->generateAll();

        $pages = file_get_contents(public_path('sitemaps/sitemap-pages.xml'));
        $this->assertStringContainsString('/live-page', $pages);
        $this->assertStringNotContainsString('draft-page', $pages);
        $this->assertStringNotContainsString('scheduled-page', $pages);

        $blog = file_get_contents(public_path('sitemaps/sitemap-blog.xml'));
        $this->assertStringContainsString('/blog/live-post', $blog);
        $this->assertStringNotContainsString('draft-post', $blog);
        $this->assertStringNotContainsString('scheduled-post', $blog);

        $products = file_get_contents(public_path('sitemaps/sitemap-parts.xml')).file_get_contents(public_path('sitemaps/sitemap-recent.xml'));
        $this->assertStringContainsString('PUBLICOEM', $products);
        $this->assertStringNotContainsString('HIDDENOEM', $products);
        $this->assertStringNotContainsString('DELETEDOEM', $products);
    }

    #[Test]
    public function adding_a_product_queues_a_rebuild_of_the_recent_products_sitemap(): void
    {
        config(['seo.live_sitemap_in_tests' => true]);
        Http::fake();
        $this->brand('audi');
        app(SitemapService::class)->generateAll();
        Bus::fake();
        app(SettingsService::class)->set('seo.sitemap_live_update', '1');

        $this->brand('opel');

        Bus::assertDispatched(RegenerateSitemapGroups::class, fn ($job) => $job->groups === ['recent']);
    }
}
