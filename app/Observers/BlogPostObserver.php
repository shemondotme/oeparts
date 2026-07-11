<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\BlogPost;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;

class BlogPostObserver
{
    public function created(BlogPost $blogPost): void
    {
        $this->log($blogPost, 'created', [], $blogPost->getAttributes());
        $this->invalidateCache($blogPost);
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
    }

    public function deleted(BlogPost $blogPost): void
    {
        $this->log($blogPost, 'deleted', $blogPost->getAttributes(), []);
        $this->invalidateCache($blogPost);
    }

    protected function invalidateCache(BlogPost $blogPost): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("blog_post.{$blogPost->id}");
            $cache->forget("blog_post.slug.{$blogPost->slug}");
            $cache->forget('sitemap_blog');
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
