<?php

namespace App\Observers;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Observers\Concerns\PushesToIndexNow;
use App\Observers\Concerns\RedirectsOnSlugChange;
use App\Services\CacheService;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\Log;

class PageObserver
{
    use PushesToIndexNow, RedirectsOnSlugChange;

    public function created(Page $page): void
    {
        $this->invalidateCache();
        $this->pushIfPublished($page);
    }

    public function updated(Page $page): void
    {
        $this->invalidateCache();
        $this->redirectIfSlugChanged($page);
        $this->pushIfPublished($page);
    }

    /**
     * The old slug's URL was only ever live if the page was published, and
     * a homepage page has no slug URL at all ("/{locale}" is its address).
     */
    protected function redirectIfSlugChanged(Page $page): void
    {
        if (! $page->wasChanged('slug') || $page->is_homepage) {
            return;
        }

        if ((string) $page->getRawOriginal('status') !== ContentStatus::Published->value) {
            return;
        }

        $this->redirectOldSlug(
            (string) $page->getOriginal('slug'),
            (string) $page->slug,
            fn (string $locale, string $slug) => "{$locale}/{$slug}"
        );
    }

    public function deleted(Page $page): void
    {
        $this->invalidateCache();
    }

    /**
     * Only products got a proactive IndexNow push — CMS pages relied
     * purely on the once-daily sitemap regeneration + organic crawl.
     * is_homepage pages are skipped: their reachable URL is "/{locale}/",
     * not "/{locale}/{slug}" (SitemapService::generatePagesSitemap()
     * excludes them from its own slug-based entries the same way).
     */
    protected function pushIfPublished(Page $page): void
    {
        if ($page->status !== ContentStatus::Published || $page->is_homepage) {
            return;
        }

        $this->pushToIndexNow(array_map(
            fn (string $locale) => url("/{$locale}/{$page->slug}"),
            LocaleRegistry::codes()
        ));
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetHomepagePageOverride();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
            Log::warning('PageObserver: cache invalidation failed: '.$e->getMessage());
        }
    }
}
