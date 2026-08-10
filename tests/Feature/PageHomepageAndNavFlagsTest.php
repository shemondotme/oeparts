<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Page.is_homepage/is_header/is_footer (PageResource) had no reader
 * anywhere — the form's own helper text promised these would change the
 * homepage and header/footer nav, but toggling them had zero effect.
 */
class PageHomepageAndNavFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPage(array $attrs = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => 'Warranty'], 'slug' => 'warranty-' . uniqid(),
            'content' => ['en' => 'Warranty details go here.'],
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'created_by' => Admin::factory()->create()->id,
        ], $attrs));
    }

    #[Test]
    public function a_page_flagged_as_homepage_replaces_the_section_builder_homepage(): void
    {
        $page = $this->publishedPage(['is_homepage' => true, 'title' => ['en' => 'Custom Homepage Content']]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('Custom Homepage Content');
    }

    #[Test]
    public function the_section_builder_homepage_still_renders_when_no_page_is_flagged(): void
    {
        $response = $this->get('/en/');

        $response->assertOk();
    }

    #[Test]
    public function only_one_page_can_be_the_homepage_at_a_time(): void
    {
        $first = $this->publishedPage(['is_homepage' => true]);
        $second = $this->publishedPage(['is_homepage' => true]);

        $this->assertFalse($first->fresh()->is_homepage);
        $this->assertTrue($second->fresh()->is_homepage);
    }

    #[Test]
    public function a_page_flagged_for_header_nav_appears_in_the_navbar(): void
    {
        $this->publishedPage(['is_header' => true, 'title' => ['en' => 'Warranty Program']]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('Warranty Program');
    }

    #[Test]
    public function a_page_flagged_for_footer_nav_appears_in_the_footer(): void
    {
        $page = $this->publishedPage(['is_footer' => true, 'title' => ['en' => 'Careers Page']]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertSee('href="' . url('/en/' . $page->slug) . '"', false);
    }

    #[Test]
    public function an_unpublished_page_flagged_for_nav_does_not_appear(): void
    {
        $this->publishedPage(['is_header' => true, 'status' => ContentStatus::Draft, 'title' => ['en' => 'Hidden Draft Link']]);

        $response = $this->get('/en/');

        $response->assertOk();
        $response->assertDontSee('Hidden Draft Link');
    }
}
