<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The site name is appended to a page title by the template. A CMS page whose meta title already ends in
 * "| OeParts" used to come out as "… | OeParts · OeParts", and a long blog title was pushed past what a
 * search result shows (about 60 characters) by the suffix alone.
 */
class PageTitleSuffixTest extends TestCase
{
    use RefreshDatabase;

    private function title(string $html): string
    {
        preg_match('#<title>(.*?)</title>#s', $html, $m);

        return html_entity_decode(trim($m[1] ?? ''));
    }

    private function makePage(string $metaTitle): Page
    {
        return Page::create([
            'title' => ['en' => 'Policy'], 'slug' => 'policy-'.uniqid(), 'content' => ['en' => '<p>Text of the policy.</p>'],
            'meta_title' => ['en' => $metaTitle], 'status' => 'published', 'published_at' => now()->subDay(), 'created_by' => Admin::factory()->create()->id,
        ]);
    }

    private function makePost(string $metaTitle): BlogPost
    {
        return BlogPost::create([
            'title' => ['en' => 'A guide'], 'slug' => 'a-guide-'.uniqid(), 'content' => ['en' => '<p>Guide text.</p>'],
            'meta_title' => ['en' => $metaTitle], 'status' => ContentStatus::Published, 'published_at' => now()->subDay(),
            'author_id' => Admin::factory()->create()->id,
        ]);
    }

    #[Test]
    public function a_cms_page_title_that_already_names_the_site_does_not_get_it_twice(): void
    {
        $page = $this->makePage('Returns Policy — Returns & Refunds | OeParts');

        $this->assertSame('Returns Policy — Returns & Refunds | OeParts', $this->title($this->get('/en/'.$page->slug)->getContent()));
    }

    #[Test]
    public function a_cms_page_title_without_the_site_name_still_gets_it(): void
    {
        $page = $this->makePage('Returns and refunds');

        $this->assertSame('Returns and refunds · OeParts', $this->title($this->get('/en/'.$page->slug)->getContent()));
    }

    #[Test]
    public function a_blog_title_gets_the_site_name_only_while_the_whole_title_fits(): void
    {
        $short = $this->makePost('How to read a VIN');
        $long = $this->makePost('New vs Used Genuine Car Parts: What to Know Today');   // 48 characters; + " · OeParts" would be 58... still fits
        $tooLong = $this->makePost('Used Genuine Car Parts or New Ones: What Buyers Should Know');   // 59 characters

        $this->assertSame('How to read a VIN · OeParts', $this->title($this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $short->slug]))->getContent()));
        $this->assertSame('New vs Used Genuine Car Parts: What to Know Today · OeParts', $this->title($this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $long->slug]))->getContent()));
        $this->assertSame('Used Genuine Car Parts or New Ones: What Buyers Should Know', $this->title($this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $tooLong->slug]))->getContent()));
    }
}
