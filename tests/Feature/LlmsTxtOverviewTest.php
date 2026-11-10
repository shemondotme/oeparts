<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Services\SettingsService;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** llms.txt: the admin's text, then a live list of the pages, posts and brands answer engines should know. */
class LlmsTxtOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
        Cache::forget('llms-txt:overview');
    }

    private function page(string $slug, string $title, array $attrs = []): Page
    {
        return Page::create($attrs + [
            'title' => ['en' => $title], 'slug' => $slug, 'content' => ['en' => '<p>Body.</p>'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'meta_description' => ['en' => 'About this page'], 'created_by' => Admin::factory()->create()->id,
        ]);
    }

    public function test_it_lists_published_pages_posts_and_brands_with_links(): void
    {
        $this->page('shipping', 'Shipping [info]');
        BlogPost::create(['title' => ['en' => 'Brake tips'], 'slug' => 'brake-tips', 'content' => ['en' => 'x'], 'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'author_id' => Admin::factory()->create()->id]);
        Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);

        $body = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringContainsString('## Pages', $body);
        $this->assertMatchesRegularExpression('#- \[Shipping info\]\([^)]*/en/shipping\): About this page#', $body);
        $this->assertMatchesRegularExpression('#- \[Brake tips\]\([^)]*/en/blog/brake-tips\)#', $body);
        $this->assertMatchesRegularExpression('#- \[Acme\]\([^)]*/en/[^)]*acme[^)]*\)#', $body);
    }

    public function test_noindex_unpublished_and_homepage_entries_are_left_out(): void
    {
        $hidden = $this->page('secret', 'Secret');
        SeoMeta::create(['metable_type' => Page::class, 'metable_id' => $hidden->id, 'robots' => 'noindex,follow']);
        $this->page('draft', 'Draft', ['status' => ContentStatus::Draft]);
        $this->page('welcome', 'Welcome', ['is_homepage' => true]);
        $this->page('visible', 'Visible');

        $body = $this->get('/llms.txt')->getContent();

        $this->assertStringContainsString('Visible', $body);
        foreach (['Secret', 'Draft', 'Welcome'] as $title) {
            $this->assertStringNotContainsString('['.$title.']', $body);
        }
    }

    public function test_switching_the_overview_off_serves_only_the_admins_text(): void
    {
        $this->page('visible', 'Visible');
        app(SettingsService::class)->set('crawlers.llms_txt_auto', 'false');

        $this->assertStringNotContainsString('## Pages', $this->get('/llms.txt')->getContent());
    }
}
