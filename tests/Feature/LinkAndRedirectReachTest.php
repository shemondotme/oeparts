<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\RedirectType;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Redirect;
use App\Services\LinkAuditService;
use App\Services\RedirectLoopDetector;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Redirect chains through absolute same-site addresses, and blog posts that are buried deep in the index. */
class LinkAndRedirectReachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class]);
    }

    private function rule(string $from, string $to): Redirect
    {
        return Redirect::create(['from_url' => $from, 'to_url' => $to, 'type' => RedirectType::Permanent, 'is_active' => true]);
    }

    public function test_a_loop_through_an_absolute_address_on_this_site_is_found(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $this->rule('a', "https://{$host}/b");
        $this->rule('b', '/c?utm=1');

        $this->assertSame('c', (new RedirectLoopDetector)->findLoop('c', 'a'), 'c -> a -> b -> c closes back on c');
    }

    public function test_a_chain_that_leaves_the_site_is_not_a_loop(): void
    {
        $this->rule('a', 'https://other-site.example/a');

        $this->assertNull((new RedirectLoopDetector)->findLoop('x', 'a'));
    }

    public function test_old_posts_that_nothing_links_to_are_reported_as_buried_and_linked_or_recent_ones_are_not(): void
    {
        $author = Admin::factory()->create()->id;
        // default page size 10 → the first 3 pages (30 posts) are "easy to reach"
        foreach (range(1, 34) as $i) {
            BlogPost::create(['title' => ['en' => "Post $i"], 'slug' => "post-$i", 'content' => ['en' => '<p>x</p>'], 'status' => ContentStatus::Published, 'published_at' => now()->subDays($i), 'author_id' => $author]);
        }
        // post-34 .. post-31 are the oldest four → beyond page 3; link to post-33 from another post
        BlogPost::where('slug', 'post-1')->update(['content' => ['en' => '<p><a href="/en/blog/post-33">see</a></p>']]);

        $labels = array_column(app(LinkAuditService::class)->scan()['orphans'], 'label');

        $this->assertCount(3, $labels, 'post-31, post-32 and post-34 are buried; post-33 is linked');
        $this->assertStringContainsString('Post 34', implode(' ', $labels));
        $this->assertStringNotContainsString('Post 33', implode(' ', $labels));
        $this->assertStringNotContainsString('Post 5', implode(' ', $labels));
    }
}
