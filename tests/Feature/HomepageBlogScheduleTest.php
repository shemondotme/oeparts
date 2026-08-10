<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Services\SectionRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * loadBlogPosts() (the homepage "recent posts" section) only checked
 * status=published + non-null published_at — BlogController@index/@show
 * additionally require published_at <= now(), since a post can be
 * status=Published with a future date to schedule it. A scheduled-but-not-
 * yet-live post appeared on the homepage immediately, with its link 404ing
 * until the scheduled time actually arrived.
 */
class HomepageBlogScheduleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function scheduled_future_posts_are_excluded_from_the_homepage_section(): void
    {
        $live = BlogPost::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);
        $scheduled = BlogPost::factory()->create(['status' => 'published', 'published_at' => now()->addWeek()]);

        $posts = app(SectionRendererService::class)->warmHomeBlogPosts();

        $this->assertTrue($posts->contains('id', $live->id));
        $this->assertFalse($posts->contains('id', $scheduled->id));
    }
}
