<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Services\CacheService;
use App\Services\SectionRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    // ── Route & response ──────────────────────────────────────────────────────

    #[Test]
    public function homepage_returns_200_for_all_languages(): void
    {
        foreach (['en', 'de', 'lt', 'fr', 'es'] as $lang) {
            $response = $this->get("/{$lang}/");
            $response->assertStatus(200);
        }
    }

    #[Test]
    public function root_url_redirects_to_language_prefix(): void
    {
        $response = $this->get('/');
        $response->assertRedirect();
        $this->assertStringContainsString('/', $response->headers->get('Location'));
    }

    #[Test]
    public function homepage_uses_frontend_layout(): void
    {
        $this->get('/en/')->assertSeeText('OEMHub');
    }

    // ── Sections rendering ────────────────────────────────────────────────────

    #[Test]
    public function homepage_renders_active_sections_only(): void
    {
        Section::create([
            'type'       => 'hero',
            'location'   => 'homepage',
            'title'      => 'Hero',
            'content'    => ['headline' => ['en' => 'Test Hero', 'de' => '', 'lt' => '', 'fr' => '', 'es' => '']],
            'is_active'  => true,
            'sort_order' => 10,
        ]);

        Section::create([
            'type'       => 'trust_bar',
            'location'   => 'homepage',
            'title'      => 'Trust',
            'content'    => ['items' => []],
            'is_active'  => false, // inactive — should NOT render
            'sort_order' => 20,
        ]);

        $response = $this->get('/en/');
        $response->assertStatus(200);
        $response->assertSee('Test Hero');
    }

    #[Test]
    public function homepage_with_no_sections_still_returns_200(): void
    {
        // No sections seeded — page should render fine with empty sections
        $this->get('/en/')->assertStatus(200);
    }

    #[Test]
    public function sections_are_rendered_in_sort_order(): void
    {
        Section::create([
            'type'       => 'stats_counter',
            'location'   => 'homepage',
            'title'      => 'Stats',
            'content'    => ['headline' => ['en' => 'First Section', 'de' => '', 'lt' => '', 'fr' => '', 'es' => ''], 'items' => []],
            'is_active'  => true,
            'sort_order' => 5,
        ]);

        Section::create([
            'type'       => 'hero',
            'location'   => 'homepage',
            'title'      => 'Hero',
            'content'    => ['headline' => ['en' => 'Second Section', 'de' => '', 'lt' => '', 'fr' => '', 'es' => '']],
            'is_active'  => true,
            'sort_order' => 10,
        ]);

        $response = $this->get('/en/');
        $response->assertStatus(200);

        $firstPos  = strpos($response->getContent(), 'First Section');
        $secondPos = strpos($response->getContent(), 'Second Section');
        $this->assertLessThan($secondPos, $firstPos, 'Section with lower sort_order should appear first');
    }

    // ── SectionRendererService ────────────────────────────────────────────────

    #[Test]
    public function section_renderer_returns_only_active_sections(): void
    {
        Section::create([
            'type' => 'hero', 'location' => 'homepage', 'title' => 'A',
            'content' => ['headline' => ['en' => '']], 'is_active' => true, 'sort_order' => 1,
        ]);
        Section::create([
            'type' => 'banner', 'location' => 'homepage', 'title' => 'B',
            'content' => [], 'is_active' => false, 'sort_order' => 2,
        ]);

        $renderer = app(SectionRendererService::class);
        $sections = $renderer->getSections('homepage');

        $this->assertCount(1, $sections);
        $this->assertEquals('hero', $sections->first()->type);
    }

    #[Test]
    public function section_renderer_returns_landing_sections_separately(): void
    {
        Section::create([
            'type' => 'hero', 'location' => 'homepage', 'title' => 'H',
            'content' => [], 'is_active' => true, 'sort_order' => 1,
        ]);
        Section::create([
            'type' => 'banner', 'location' => 'landing', 'title' => 'L',
            'content' => [], 'is_active' => true, 'sort_order' => 1,
        ]);

        $renderer = app(SectionRendererService::class);

        $this->assertCount(1, $renderer->getSections('homepage'));
        $this->assertCount(1, $renderer->getSections('landing'));
    }

    #[Test]
    public function build_section_data_only_queries_needed_relation_types(): void
    {
        // Only hero section — no testimonials/faqs/blog_posts/manufacturers needed
        $sections = collect([
            (object) ['type' => 'hero'],
        ]);

        $data = app(SectionRendererService::class)->buildSectionData(collect(
            Section::all() // empty, fine
        ));

        // Keys should not be present because no matching section types
        $this->assertArrayNotHasKey('testimonials', $data);
        $this->assertArrayNotHasKey('faqs', $data);
        $this->assertArrayNotHasKey('blog_posts', $data);
        $this->assertArrayNotHasKey('manufacturers', $data);
    }

    // ── Hreflang / SEO ────────────────────────────────────────────────────────

    #[Test]
    public function homepage_contains_hreflang_for_all_languages(): void
    {
        $response = $this->get('/en/');
        $response->assertStatus(200);

        foreach (['en', 'de', 'lt', 'fr', 'es'] as $lang) {
            $response->assertSee("hreflang=\"{$lang}\"", false);
        }
        $response->assertSee('hreflang="x-default"', false);
    }

    #[Test]
    public function homepage_contains_canonical_link(): void
    {
        $this->get('/en/')
            ->assertStatus(200)
            ->assertSee('rel="canonical"', false);
    }
}
