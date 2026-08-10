<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BlogController::index()'s category/tag sidebar counts used BlogPost::
 * published() (status-only), while the actual post list applied an extra
 * published_at <= now() check — a category whose only post was scheduled
 * for the future showed a non-zero, clickable sidebar count that led to an
 * empty result page until the scheduled time arrived. Fixed by making the
 * shared scopePublished() itself date-aware.
 */
class BlogScheduledSidebarCountsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_category_whose_only_post_is_scheduled_for_the_future_shows_no_sidebar_count(): void
    {
        $category = Category::factory()->create();
        BlogPost::factory()->create([
            'category_id' => $category->id, 'status' => 'published', 'published_at' => now()->addWeek(),
        ]);

        $response = $this->get('/en/blog');

        $response->assertOk();
        $response->assertViewHas('categories', function ($categories) use ($category) {
            return ! $categories->contains('id', $category->id);
        });
    }

    #[Test]
    public function a_category_with_a_live_post_still_shows_its_count(): void
    {
        $category = Category::factory()->create();
        BlogPost::factory()->create([
            'category_id' => $category->id, 'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/en/blog');

        $response->assertOk();
        $response->assertViewHas('categories', function ($categories) use ($category) {
            $match = $categories->firstWhere('id', $category->id);

            return $match && $match->blog_posts_count === 1;
        });
    }
}
