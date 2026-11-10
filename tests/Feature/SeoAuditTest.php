<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\RedirectType;
use App\Filament\Pages\Settings\SeoAudit;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Manufacturer;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Services\SeoAuditService;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The SEO Audit lists real, fixable problems — and stays quiet about things
 * that are fine.
 */
class SeoAuditTest extends TestCase
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

    protected function tearDown(): void
    {
        @unlink(public_path('sitemap.xml'));

        parent::tearDown();
    }

    /** @return array<string, array<string, mixed>> checks keyed by id */
    private function audit(): array
    {
        $checks = [];
        foreach (app(SeoAuditService::class)->run()['checks'] as $check) {
            $checks[$check['id']] = $check;
        }

        return $checks;
    }

    private function page(array $attrs = []): Page
    {
        return Page::create($attrs + [
            'title' => ['en' => 'About'], 'slug' => 'about', 'content' => ['en' => 'x'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    private function redirect(string $from, string $to): Redirect
    {
        return Redirect::create(['from_url' => $from, 'to_url' => $to, 'type' => RedirectType::Permanent, 'is_active' => true, 'hit_count' => 0]);
    }

    // ── Configuration ─────────────────────────────────────────────────────

    #[Test]
    public function a_non_production_environment_is_critical_and_production_passes(): void
    {
        $this->assertSame('critical', $this->audit()['env']['severity']); // the test environment is "testing"

        $this->app['env'] = 'production';
        $this->assertSame('ok', $this->audit()['env']['severity']);
    }

    #[Test]
    public function a_local_app_url_and_a_noindex_default_are_critical(): void
    {
        config(['app.url' => 'http://localhost']);
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'default_robots'], ['value' => 'noindex,follow', 'type' => 'string', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('seo');

        $checks = $this->audit();

        $this->assertSame('critical', $checks['app_url']['severity']);
        $this->assertSame('critical', $checks['default_robots']['severity']);
        $this->assertStringContainsString('tab=4', $checks['default_robots']['fix_url']);
    }

    #[Test]
    public function a_sitemap_full_of_localhost_urls_is_critical(): void
    {
        file_put_contents(public_path('sitemap.xml'), '<sitemapindex><sitemap><loc>http://localhost/sitemaps/sitemap-parts.xml</loc></sitemap></sitemapindex>');

        $this->assertSame('critical', $this->audit()['sitemap']['severity']);
    }

    #[Test]
    public function a_good_recent_sitemap_passes(): void
    {
        file_put_contents(public_path('sitemap.xml'), '<sitemapindex><sitemap><loc>https://live.example.org/sitemaps/sitemap-parts.xml</loc></sitemap></sitemapindex>');

        $this->assertSame('ok', $this->audit()['sitemap']['severity']);
    }

    // ── Content ───────────────────────────────────────────────────────────

    #[Test]
    public function pages_without_their_own_search_text_are_listed_with_an_edit_link(): void
    {
        $page = $this->page(['slug' => 'bare']);

        $check = $this->audit()['meta_missing'];

        $this->assertSame('warning', $check['severity']);
        $this->assertSame(1, $check['count']);
        $this->assertStringContainsString("/{$page->id}/edit", $check['items'][0]['url']);
    }

    #[Test]
    public function a_page_with_full_search_text_passes(): void
    {
        $this->page(['meta_title' => ['en' => 'A perfectly sized page title for search'], 'meta_description' => ['en' => str_repeat('d', 100)]]);

        $this->assertSame('ok', $this->audit()['meta_missing']['severity']);
    }

    #[Test]
    public function over_long_titles_and_duplicate_titles_are_reported(): void
    {
        $title = str_repeat('x', 70);
        $this->page(['slug' => 'a', 'meta_title' => ['en' => $title], 'meta_description' => ['en' => str_repeat('d', 100)]]);
        $this->page(['slug' => 'b', 'meta_title' => ['en' => $title], 'meta_description' => ['en' => str_repeat('d', 100)]]);

        $checks = $this->audit();

        $this->assertSame(2, $checks['meta_length']['count']);
        $this->assertSame('warning', $checks['meta_duplicate']['severity']);
        $this->assertSame(2, $checks['meta_duplicate']['count']);
    }

    #[Test]
    public function brands_and_models_without_an_intro_are_opportunities_and_disappear_once_written(): void
    {
        $brand = Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);
        $model = CarModel::create(['manufacturer_id' => $brand->id, 'name' => 'Rocket', 'slug' => 'rocket', 'is_active' => true]);

        $checks = $this->audit();
        $this->assertSame('info', $checks['brand_intro']['severity']);
        $this->assertSame('info', $checks['model_intro']['severity']);

        $brand->update(['intro' => ['en' => 'Real text.']]);
        $model->update(['intro' => ['en' => 'Real text.']]);

        $checks = $this->audit();
        $this->assertArrayNotHasKey('brand_intro', $checks);
        $this->assertArrayNotHasKey('model_intro', $checks);
    }

    #[Test]
    public function blog_posts_without_a_featured_image_are_listed(): void
    {
        BlogPost::create([
            'title' => ['en' => 'T'], 'slug' => 't', 'content' => ['en' => 'x'], 'status' => ContentStatus::Published,
            'published_at' => now()->subDay(), 'author_id' => Admin::factory()->create()->id,
        ]);

        $this->assertSame(1, $this->audit()['blog_no_image']['count']);
    }

    // ── Indexing & redirects ──────────────────────────────────────────────

    #[Test]
    public function noindex_items_and_open_404s_are_counted(): void
    {
        $page = $this->page();
        SeoMeta::create(['metable_type' => Page::class, 'metable_id' => $page->id, 'robots' => 'noindex,follow']);
        NotFoundLog::recordHit('en/missing', 'en', null, '203.0.113.9');

        $checks = $this->audit();

        $this->assertSame(1, $checks['noindex']['count']);
        $this->assertSame(1, $checks['not_found']['count']);
        $this->assertSame('info', $checks['not_found']['severity']);
    }

    #[Test]
    public function redirect_loops_are_critical_and_chains_are_warnings(): void
    {
        $this->redirect('en/a', '/en/b');
        $this->redirect('en/b', '/en/a');
        $this->redirect('en/x', '/en/y');
        $this->redirect('en/y', '/en/z');

        $checks = $this->audit();

        $this->assertSame('critical', $checks['redirect_loops']['severity']);
        $this->assertSame('warning', $checks['redirect_chains']['severity']);
        $this->assertStringContainsString('/en/x', $checks['redirect_chains']['items'][0]['label'] ?? '');
    }

    #[Test]
    public function without_redirects_the_loop_check_passes_and_no_chain_is_reported(): void
    {
        $checks = $this->audit();

        $this->assertSame('ok', $checks['redirect_loops']['severity']);
        $this->assertArrayNotHasKey('redirect_chains', $checks);
    }

    // ── The page ──────────────────────────────────────────────────────────

    #[Test]
    public function the_page_renders_the_summary_and_the_most_serious_problems_first(): void
    {
        $this->redirect('en/a', '/en/b');
        $this->redirect('en/b', '/en/a');

        $component = Livewire::test(SeoAudit::class)->assertOk()->assertSee('SEO Audit')->assertSee('Redirect loops')->assertSee('Critical');

        $html = $component->html();
        $this->assertLessThan(strpos($html, 'data-check="meta_missing"') ?: PHP_INT_MAX, strpos($html, 'data-check="redirect_loops"'));
    }

    #[Test]
    public function only_admins_can_open_it(): void
    {
        $this->assertTrue(SeoAudit::canAccess());

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->assertFalse(SeoAudit::canAccess());
    }
}
