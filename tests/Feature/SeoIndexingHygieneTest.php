<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Services\SitemapService;
use App\Support\PublicBaseUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Second SEO-audit round: what the sitemap lists must match what the site
 * serves; renamed slugs keep their old URLs alive; internal-search and
 * out-of-range listing URLs are not indexable duplicates; robots.txt closes
 * the gaps its prefix rules left; and no absolute URL leaves the system with
 * a local host.
 */
class SeoIndexingHygieneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []); // parallel runs share this real folder
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    private function page(array $attrs = []): Page
    {
        return Page::create($attrs + [
            'title' => ['en' => 'About'], 'slug' => 'about', 'content' => ['en' => '<p>x</p>'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    private function sitemapPages(): string
    {
        app(SitemapService::class)->generateAll();

        return file_get_contents(public_path('sitemaps/sitemap-pages.xml'));
    }

    // ── Sitemap lists only what the site actually serves ─────────────────

    #[Test]
    public function a_scheduled_or_undated_page_is_not_in_the_sitemap_because_it_404s(): void
    {
        $this->page(['slug' => 'live']);
        $this->page(['slug' => 'scheduled', 'published_at' => now()->addWeek()]);
        $this->page(['slug' => 'undated', 'published_at' => null]);

        $xml = $this->sitemapPages();

        $this->assertStringContainsString('/en/live</loc>', $xml);
        $this->assertStringNotContainsString('/en/scheduled</loc>', $xml);
        $this->assertStringNotContainsString('/en/undated</loc>', $xml);
        $this->get('/en/scheduled')->assertNotFound();
    }

    #[Test]
    public function a_page_set_to_noindex_in_seo_meta_is_left_out_of_the_sitemap(): void
    {
        $indexed = $this->page(['slug' => 'indexed']);
        $hidden = $this->page(['slug' => 'hidden']);
        SeoMeta::create(['metable_type' => Page::class, 'metable_id' => $hidden->id, 'robots' => 'noindex,follow']);
        SeoMeta::create(['metable_type' => Page::class, 'metable_id' => $indexed->id, 'robots' => 'index,follow']);

        $xml = $this->sitemapPages();

        $this->assertStringContainsString('/en/indexed</loc>', $xml);
        $this->assertStringNotContainsString('/en/hidden</loc>', $xml);
    }

    // ── Renamed slugs keep working ────────────────────────────────────────

    #[Test]
    public function renaming_a_published_page_slug_301s_the_old_url_in_every_locale(): void
    {
        $page = $this->page(['slug' => 'old-name']);

        $page->update(['slug' => 'new-name']);

        foreach (['en', 'de', 'lt', 'fr', 'es'] as $locale) {
            $this->assertDatabaseHas('redirects', ['from_url' => "{$locale}/old-name", 'to_url' => "/{$locale}/new-name"]);
        }
    }

    #[Test]
    public function a_draft_pages_slug_change_creates_no_redirects(): void
    {
        $page = $this->page(['slug' => 'draft-a', 'status' => ContentStatus::Draft]);

        $page->update(['slug' => 'draft-b']);

        $this->assertSame(0, Redirect::count());
    }

    #[Test]
    public function renaming_a_slug_back_does_not_leave_a_circular_redirect(): void
    {
        $page = $this->page(['slug' => 'a']);

        $page->update(['slug' => 'b']);
        $page->update(['slug' => 'a']);

        $this->assertDatabaseMissing('redirects', ['from_url' => 'en/a']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/b', 'to_url' => '/en/a']);
    }

    #[Test]
    public function renaming_a_brand_redirects_the_brand_models_list_and_every_model_page(): void
    {
        $brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        CarModel::create(['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'is_active' => true]);

        $brand->update(['slug' => 'acme-gmbh']);

        $this->assertDatabaseHas('redirects', ['from_url' => 'en/brand/acme', 'to_url' => '/en/brand/acme-gmbh']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/brand/acme/models', 'to_url' => '/en/brand/acme-gmbh/models']);
        $this->assertDatabaseHas('redirects', ['from_url' => 'en/brand/acme/rocket', 'to_url' => '/en/brand/acme-gmbh/rocket']);
    }

    #[Test]
    public function renaming_a_blog_post_slug_redirects_the_old_post_url(): void
    {
        $post = BlogPost::create([
            'title' => ['en' => 'T'], 'slug' => 'first-title', 'content' => ['en' => 'x'], 'excerpt' => ['en' => 'x'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(),
            'author_id' => Admin::factory()->create()->id,
        ]);

        $post->update(['slug' => 'better-title']);

        $this->assertDatabaseHas('redirects', ['from_url' => 'en/blog/first-title', 'to_url' => '/en/blog/better-title']);
    }

    // ── Listing hygiene ───────────────────────────────────────────────────

    #[Test]
    public function blog_internal_search_results_are_noindex_and_canonicalize_to_the_plain_index(): void
    {
        $html = $this->get('/en/blog?search=brake')->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/en/blog').'">', $html);
    }

    #[Test]
    public function the_plain_blog_index_stays_indexable(): void
    {
        $html = $this->get('/en/blog')->getContent();

        $this->assertStringNotContainsString('content="noindex', $html);
    }

    #[Test]
    public function brand_letter_filter_is_noindex_and_a_page_past_the_end_is_a_404(): void
    {
        $this->get('/en/brands?letter=A')->assertSee('<meta name="robots" content="noindex,follow">', false);
        $this->get('/en/brands?page=99')->assertNotFound();
        $this->get('/en/blog?page=99')->assertNotFound();
    }

    // ── robots.txt ────────────────────────────────────────────────────────

    #[Test]
    public function robots_blocks_the_cart_and_checkout_pages_themselves_and_non_content_endpoints(): void
    {
        // Forcing 'production' also defeats RedirectIfNotInstalled's testing
        // carve-out, so that middleware needs its own explicit bypass.
        $this->withoutMiddleware(\App\Http\Middleware\RedirectIfNotInstalled::class);
        $this->app['env'] = 'production';

        $body = $this->get('/robots.txt')->getContent();

        foreach (['Disallow: /*/cart$', 'Disallow: /*/checkout$', 'Disallow: /*/auth/', 'Disallow: /*/search/', 'Disallow: /*?search=', 'Disallow: /health'] as $rule) {
            $this->assertStringContainsString($rule, $body);
        }
        // Content must stay crawlable.
        $this->assertStringNotContainsString("Disallow: /\n", $body);
        $this->assertStringContainsString('Allow: /', $body);
    }

    // ── No absolute URL leaves with a local host ──────────────────────────

    #[Test]
    public function local_host_urls_are_re_rooted_onto_the_public_address(): void
    {
        config(['app.url' => 'http://localhost']);
        Setting::updateOrCreate(['group' => 'general', 'key' => 'site_url'], ['value' => 'https://live.example.org', 'type' => 'string', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('general');
        $settings = app(SettingsService::class);

        $this->assertSame('https://live.example.org/en/blog/x?y=1', PublicBaseUrl::rewrite('http://localhost/en/blog/x?y=1', $settings));
        $this->assertSame('https://other.com/a', PublicBaseUrl::rewrite('https://other.com/a', $settings), 'a real host is never touched');
        $this->assertSame('/en/a', PublicBaseUrl::rewrite('/en/a', $settings), 'a relative path is never touched');
    }
}
