<?php

namespace App\Jobs;

use App\Services\SitemapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds ONLY the small sitemap groups (blog, pages) a moment after something in them was saved, so a new or
 * edited post shows up in the sitemap in seconds instead of at the next nightly run. The big product files are
 * never touched here.
 *
 * Unique per group while one is still waiting, so ten quick edits in a row cause one rebuild. A full
 * generation holding the lock simply skips this run: the nightly run (or the one in progress) covers it.
 */
class RegenerateSitemapGroups implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /** Seconds a waiting rebuild blocks a duplicate. */
    public int $uniqueFor = 120;

    /** @param array<int, string> $groups */
    public function __construct(public array $groups)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return implode(',', $this->groups);
    }

    /**
     * Queue a rebuild of one small group, if the admin left "update the sitemap when content is saved" on
     * and the sitemap exists at all (a first full generation has to come from the button or the schedule).
     */
    public static function queueFor(string $group): void
    {
        // A bulk import saves thousands of products a minute: one queue attempt per group per half minute per
        // process is plenty (the job itself is unique anyway), and it keeps the import free of per-row overhead.
        static $lastAttempt = [];
        if ($group === 'recent' && ! app()->runningUnitTests()) {
            if (isset($lastAttempt[$group]) && microtime(true) - $lastAttempt[$group] < 30) {
                return;
            }
            $lastAttempt[$group] = microtime(true);
        }

        // The test suite saves pages and posts everywhere with a synchronous queue; rebuilding sitemap files on
        // each of those would slow it down and race on public/sitemaps. The tests of this feature opt in.
        if (app()->runningUnitTests() && ! config('seo.live_sitemap_in_tests')) {
            return;
        }

        try {
            if (! filter_var(settings('seo.sitemap_live_update', false), FILTER_VALIDATE_BOOLEAN)
                || ! filter_var(settings('seo.sitemap_include_'.($group === 'recent' ? 'products' : $group), true), FILTER_VALIDATE_BOOLEAN)
                || ! is_file(public_path('sitemap.xml'))) {
                return;
            }

            // Blog posts and pages are rare and cheap; products arrive in bursts, so they wait a little longer.
            static::dispatch([$group])->delay(now()->addSeconds($group === 'recent' ? 120 : 20));
        } catch (\Throwable $e) {
            // A sitemap refresh must never break saving a post or a page.
            Log::warning('Live sitemap update not queued: '.$e->getMessage());
        }
    }

    public function handle(SitemapService $sitemaps): void
    {
        try {
            $sitemaps->generateAll($this->groups);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'already in progress')) {
                return;
            }

            throw $e;
        }
    }
}
