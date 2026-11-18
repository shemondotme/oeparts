<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Jobs\RegenerateSitemapGroups;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Console\Command;

/**
 * A post or page scheduled for a future time goes public when that time arrives — nobody saves anything then,
 * so the save-triggered sitemap update never fires for it. This runs every few minutes and, when something
 * became public in the last stretch, queues a rebuild of just that small sitemap file.
 *
 * Stateless on purpose: it looks at a window that overlaps the previous run, so a missed run or a cleared cache
 * loses nothing, and a repeated rebuild is harmless (the job is unique).
 */
class SitemapPublishScheduled extends Command
{
    protected $signature = 'sitemap:publish-scheduled {--minutes=15 : How far back to look for newly public content}';

    protected $description = 'Add posts and pages whose scheduled publish time has just passed to the sitemap';

    public function handle(): int
    {
        $from = now()->subMinutes(max(1, (int) $this->option('minutes')));
        $queued = [];

        $became = fn (string $model) => $model::where('status', ContentStatus::Published->value)
            ->where('published_at', '>', $from)
            ->where('published_at', '<=', now())
            ->exists();

        if ($became(BlogPost::class)) {
            RegenerateSitemapGroups::queueFor('blog');
            $queued[] = 'blog';
        }

        if ($became(Page::class)) {
            RegenerateSitemapGroups::queueFor('pages');
            $queued[] = 'pages';
        }

        $this->info($queued === [] ? 'Nothing new went public.' : 'Queued a sitemap update for: '.implode(', ', $queued).'.');

        return self::SUCCESS;
    }
}
