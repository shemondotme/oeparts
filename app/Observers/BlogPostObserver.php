<?php

namespace App\Observers;

use App\Enums\ContentStatus;
use App\Models\ActivityLog;
use App\Models\BlogPost;
use App\Observers\Concerns\PushesToIndexNow;
use App\Services\CacheService;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\Auth;

class BlogPostObserver
{
    use PushesToIndexNow;

    public function created(BlogPost $blogPost): void
    {
        $this->log($blogPost, 'created', [], $blogPost->getAttributes());
        $this->invalidateCache($blogPost);
        $this->pushIfPublished($blogPost);
    }

    public function updated(BlogPost $blogPost): void
    {
        $original = $blogPost->getOriginal();
        $changes = $blogPost->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($blogPost, 'updated', $original, $changes);
        }

        $this->invalidateCache($blogPost);
        $this->pushIfPublished($blogPost);
    }

    public function deleted(BlogPost $blogPost): void
    {
        $this->log($blogPost, 'deleted', $blogPost->getAttributes(), []);
        $this->invalidateCache($blogPost);
    }

    /**
     * Only products got a proactive IndexNow push — blog posts relied
     * purely on the once-daily sitemap regeneration + organic crawl. Only
     * pushes when the post is actually publicly live (matches
     * SitemapService::generateBlogSitemap()'s own visibility condition) —
     * a draft or future-scheduled post has no reachable URL to announce.
     */
    protected function pushIfPublished(BlogPost $blogPost): void
    {
        if ($blogPost->status !== ContentStatus::Published || $blogPost->published_at?->isFuture()) {
            return;
        }

        $this->pushToIndexNow(array_map(
            fn (string $locale) => route('frontend.blog.show', ['lang' => $locale, 'slug' => $blogPost->slug]),
            LocaleRegistry::codes()
        ));
    }

    protected function invalidateCache(BlogPost $blogPost): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("blog_post.{$blogPost->id}");
            $cache->forget("blog_post.slug.{$blogPost->slug}");
            $cache->forget('sitemap_blog');
            $cache->forgetHomeBlogPosts();
            $cache->forgetBlogFeaturedPost();
            $cache->forgetBlogFilters();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(BlogPost $blogPost, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($blogPost),
                'model_id' => $blogPost->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
