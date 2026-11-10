<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Thin pages are kept out of search automatically (opt-in), in the language where they are thin. */
class ThinContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
    }

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), glob(public_path('sitemaps/*.xml')) ?: []);
        @unlink(public_path('sitemap.xml'));
        parent::tearDown();
    }

    private function page(string $slug, array $content): Page
    {
        return Page::create([
            'title' => ['en' => ucfirst($slug)], 'slug' => $slug, 'content' => $content,
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    private function enable(int $min = 50): void
    {
        app(SettingsService::class)->set('seo.thin_content_noindex', 'true');
        app(SettingsService::class)->set('seo.thin_content_min_words', (string) $min);
    }

    public function test_it_is_off_by_default(): void
    {
        $page = $this->page('tiny', ['en' => '<p>Hi.</p>']);

        $this->assertFalse(app(SeoService::class)->isThinContent($page, 'en'));
        $this->get('/en/tiny')->assertOk()->assertDontSee('noindex,follow', false);
    }

    public function test_a_thin_page_is_served_noindex_and_a_full_one_is_not(): void
    {
        $this->enable();
        $this->page('tiny', ['en' => '<p>Hi there.</p>']);
        $this->page('full', ['en' => '<p>'.str_repeat('A useful sentence about the part. ', 30).'</p>']);

        $this->get("/en/tiny")->assertOk()->assertSee("<meta name=\"robots\" content=\"noindex,follow\">", false);
        $this->get('/en/full')->assertOk()->assertDontSee('content="noindex,follow"', false);
    }

    public function test_the_admins_own_robots_choice_wins(): void
    {
        $this->enable();
        $page = $this->page('tiny', ['en' => '<p>Hi.</p>']);
        SeoMeta::create(['metable_type' => Page::class, 'metable_id' => $page->id, 'robots' => 'index,follow']);

        $this->get('/en/tiny')->assertOk()->assertSee('<meta name="robots" content="index,follow">', false);
    }

    public function test_thinness_is_judged_per_language_and_the_sitemap_omits_only_the_thin_one(): void
    {
        $this->enable();
        $page = $this->page('guide', ['en' => '<p>'.str_repeat('Plenty of words in this sentence. ', 30).'</p>', 'de' => '<p>Kurz.</p>']);
        $seo = app(SeoService::class);

        $this->assertFalse($seo->isThinContent($page, 'en'));
        $this->assertTrue($seo->isThinContent($page, 'de'));

        Http::fake();
        app(SitemapService::class)->generateAll();
        $xml = file_get_contents(public_path('sitemaps/sitemap-pages.xml'));

        $this->assertMatchesRegularExpression('#<loc>[^<]*/en/guide</loc>#', $xml);
        $this->assertDoesNotMatchRegularExpression('#<loc>[^<]*/de/guide</loc>#', $xml);
        $this->assertStringNotContainsString('hreflang="de" href="http://localhost/de/guide"', $xml);
    }

    public function test_the_homepage_is_never_thin(): void
    {
        $this->enable();
        $home = $this->page('home', ['en' => '<p>Hi.</p>']);
        $home->update(['is_homepage' => true]);

        $this->assertFalse(app(SeoService::class)->isThinContent($home->fresh(), 'en'));
    }
}
