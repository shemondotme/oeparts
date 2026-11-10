<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Models\Page;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What Facebook / LinkedIn / WhatsApp read when a link is shared. Their own debuggers need a public address, so
 * this checks the thing they check: every shareable page carries a complete, consistent set of social tags.
 */
class OgTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
    }

    /** @return array<string, string> property/name => content (first occurrence) */
    private function tags(string $html): array
    {
        $doc = new \DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $tags = [];
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            $key = $meta->getAttribute('property') ?: $meta->getAttribute('name');
            if ($key !== '' && ! isset($tags[$key])) {
                $tags[$key] = $meta->getAttribute('content');
            }
        }

        return $tags;
    }

    private function assertCompleteShareCard(string $url): void
    {
        $tags = $this->tags($this->get($url)->assertOk()->getContent());

        foreach (['og:title', 'og:description', 'og:url', 'og:type', 'og:site_name', 'og:image', 'twitter:card', 'twitter:title', 'twitter:description'] as $key) {
            $this->assertNotEmpty($tags[$key] ?? '', "$url is missing $key");
        }
        $this->assertMatchesRegularExpression('#^https?://#', $tags['og:image'], "$url: og:image must be an absolute address");
        $this->assertMatchesRegularExpression('#^https?://#', $tags['og:url'], "$url: og:url must be absolute");
        $this->assertLessThanOrEqual(300, mb_strlen($tags['og:description']), "$url: a very long description is cut by the platforms");
        $this->assertSame($tags['og:title'], $tags['twitter:title']);
    }

    public function test_home_page_blog_brand_and_search_pages_all_carry_a_complete_share_card(): void
    {
        app(\App\Services\SettingsService::class)->set('seo.default_og_image', 'og/default.png');
        $author = Admin::factory()->create()->id;
        Page::create(['title' => ['en' => 'Guide'], 'slug' => 'guide', 'content' => ['en' => '<p>'.str_repeat('Words here. ', 40).'</p>'], 'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false, 'created_by' => $author]);
        BlogPost::create(['title' => ['en' => 'A post'], 'slug' => 'a-post', 'content' => ['en' => '<p>'.str_repeat('Words here. ', 40).'</p>'], 'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'author_id' => $author]);
        Manufacturer::create(['name' => ['en' => 'Acme'], 'slug' => 'acme', 'country_code' => 'DE', 'is_active' => true]);

        foreach (['/en', '/en/guide', '/en/blog/a-post', '/en/blog', '/en/brands', '/en/brand/acme'] as $url) {
            $this->assertCompleteShareCard($url);
        }
    }

    public function test_the_audit_flags_a_missing_default_share_image(): void
    {
        $ids = fn () => array_column(app(\App\Services\SeoAuditService::class)->run(true)['checks'], 'id');

        $this->assertContains('share_image', $ids());

        app(\App\Services\SettingsService::class)->set('seo.default_og_image', 'og/default.png');
        $this->assertNotContains('share_image', $ids());
    }
}
