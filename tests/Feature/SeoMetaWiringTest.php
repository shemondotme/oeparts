<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Page;
use App\Models\SeoMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SeoMeta (SeoMetaResource) had no creation path anywhere (its own
 * standalone form can't set metable_type/id) and no render path either —
 * PageController/BlogController built meta straight from the entity's own
 * meta_title/meta_description, bypassing SeoMeta's canonical/OG/robots
 * fields entirely. Wired in as an "Advanced SEO" section embedded directly
 * on the Page/BlogPost edit screens (a nested singular relationship, not a
 * standalone SeoMetaResource record), and read back on the real render
 * paths.
 */
class SeoMetaWiringTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPage(array $attrs = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'Warranty'], 'slug' => 'warranty-' . uniqid(),
            'content' => ['en' => 'Warranty details.'],
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'created_by' => Admin::factory()->create()->id,
        ], $attrs));
    }

    #[Test]
    public function saving_the_advanced_seo_section_creates_a_seo_meta_row_for_the_page(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['view pages', 'edit pages']);
        $this->actingAs($admin, 'admin');

        $page = $this->publishedPage();

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm([
                'seoMeta.canonical_url' => 'https://oeparts.example/canonical-target',
                'seoMeta.og_title' => 'Custom OG Title',
                'seoMeta.robots' => 'noindex,follow',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $meta = SeoMeta::where('metable_type', Page::class)->where('metable_id', $page->id)->first();
        $this->assertNotNull($meta, 'SeoMeta row should have been created via the embedded relationship');
        $this->assertSame('https://oeparts.example/canonical-target', $meta->canonical_url);
        $this->assertSame('Custom OG Title', $meta->og_title);
        $this->assertSame('noindex,follow', $meta->robots);
    }

    #[Test]
    public function a_pages_canonical_override_renders_on_the_frontend(): void
    {
        $page = $this->publishedPage();
        SeoMeta::create([
            'metable_type' => Page::class, 'metable_id' => $page->id,
            'canonical_url' => 'https://oeparts.example/custom-canonical',
            'robots' => 'noindex,nofollow',
        ]);

        $response = $this->get('/en/' . $page->slug);

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="https://oeparts.example/custom-canonical">', false);
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    #[Test]
    public function a_page_without_seo_meta_falls_back_to_the_default_canonical_url(): void
    {
        $page = $this->publishedPage();

        $response = $this->get('/en/' . $page->slug);

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="' . url('/en/' . $page->slug) . '">', false);
    }

    #[Test]
    public function a_blog_posts_og_override_renders_on_the_frontend(): void
    {
        $admin = Admin::factory()->create();
        $post = BlogPost::create([
            'title' => ['en' => 'How To Choose Brake Pads'],
            'slug' => 'how-to-choose-brake-pads-' . uniqid(),
            'content' => ['en' => 'Content body.'],
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'author_id' => $admin->id,
        ]);
        SeoMeta::create([
            'metable_type' => BlogPost::class, 'metable_id' => $post->id,
            'og_title' => 'Custom Social Share Title',
        ]);

        $response = $this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]));

        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="Custom Social Share Title">', false);
    }
}
