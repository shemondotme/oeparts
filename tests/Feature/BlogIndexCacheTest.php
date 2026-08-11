<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BlogController::index() ran three queries unconditionally on every single
 * /blog visit regardless of category/tag/search filters (featured post,
 * category counts, tags) — none cached, unlike the equivalent homepage
 * blog-preview widget (CacheService::rememberHomeBlogPosts()). The actual
 * filtered/paginated post listing itself is deliberately left uncached
 * (it's "search" work, like SearchService's own fresh-per-query results).
 *
 * Laravel's Cache::remember()/has() both treat a cached null as "not
 * cached" (has() is literally `! is_null(get())`, and remember() re-invokes
 * its callback whenever the stored value reads back null) — so these tests
 * verify actual query REUSE on a repeat visit (via the query log) rather
 * than relying on Cache::has(), and the featured-post case uses a fixture
 * that genuinely has a featured image so its cache is actually exercised.
 */
class BlogIndexCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_blog_index_page_reuses_cached_category_and_tag_queries_on_repeat_visits(): void
    {
        $this->get('/en/blog')->assertOk();
        $this->assertTrue(Cache::has('blog.categories'));
        $this->assertTrue(Cache::has('blog.tags'));

        DB::enableQueryLog();
        $this->get('/en/blog')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertStringNotContainsString('"categories"', $queries, 'a repeat visit must not re-query categories');
        $this->assertStringNotContainsString('"blog_tags"', $queries, 'a repeat visit must not re-query tags');
    }

    #[Test]
    public function a_repeat_visit_does_not_re_run_the_featured_post_query(): void
    {
        $admin = Admin::factory()->create();
        $media = MediaFile::create([
            'uploaded_by' => $admin->id, 'file_name' => 'test.png', 'file_path' => 'media/test.png',
            'file_url' => '/storage/media/test.png', 'mime_type' => 'image/png', 'size' => 2048,
        ]);
        BlogPost::factory()->create([
            'status' => 'published', 'published_at' => now()->subDay(), 'featured_image_id' => $media->id,
        ]);

        $this->get('/en/blog')->assertOk();

        DB::enableQueryLog();
        $this->get('/en/blog')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertStringNotContainsString('featured_image_id', $queries, 'a repeat visit must not re-query the featured post');
    }

    #[Test]
    public function a_new_post_in_a_category_invalidates_the_stale_sidebar_count(): void
    {
        $category = Category::factory()->create();

        $this->get('/en/blog');
        $this->assertTrue(Cache::has('blog.categories'));

        BlogPost::factory()->create([
            'category_id' => $category->id, 'status' => 'published', 'published_at' => now()->subMinute(),
        ]);

        $this->assertFalse(Cache::has('blog.categories'), 'BlogPostObserver must forget the stale category counts');

        $response = $this->get('/en/blog');
        $response->assertViewHas('categories', function ($categories) use ($category) {
            $match = $categories->firstWhere('id', $category->id);

            return $match && $match->blog_posts_count === 1;
        });
    }
}
