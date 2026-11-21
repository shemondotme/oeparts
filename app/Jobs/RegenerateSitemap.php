<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\SitemapService;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued wrapper around SitemapService::generateAll(), used by the "Regenerate
 * sitemap now" action on SeoSettings instead of calling the service inline.
 * At ~100k products (500k+ <loc> entries once split across locales),
 * generation can run long enough to risk the web request's timeout. On an
 * install with a real queue worker this defers the work off the request; on
 * a QUEUE_CONNECTION=sync install (shared hosting — no worker
 * required) SyncQueue runs handle() inline in the same call, identical to
 * today, so this is never a regression there.
 *
 * Notifies the triggering admin's roles via the bell (not email — this is a
 * routine, cheaply re-run operation, not an integrity-critical one like a
 * backup failure).
 */
class RegenerateSitemap implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * A big catalogue (a million parts, five languages, language alternates on every entry) takes well over ten minutes (one hour is the most the redis queue allows before it hands a job to a second worker: retry_after is 3700).
     * One try only: a second attempt would just meet the generation lock of the first.
     */
    public int $timeout = 3600;

    public function __construct(
        public string $triggeredBy,
    ) {
        $this->onQueue('default');
    }

    public function handle(SitemapService $sitemaps): void
    {
        // A catalogue this size cannot be built inside the queue's one-hour job limit: the job would be killed at the
        // limit and leave a half-written sitemap (a real catalogue of 1.76M parts took 62 minutes from the command
        // line, and the queued run failed at the hour mark on 2026-11-19 and 2026-11-21). Say so, and leave the files
        // alone: the nightly schedule (`sitemap:generate`, a command-line run with no time limit) builds it.
        $limit = (int) config('queue.sitemap_product_limit', 800000);

        if ($limit > 0) {
            $products = Product::query()->where('is_active', true)->count();

            if ($products > $limit) {
                Log::warning('Sitemap regeneration was not queued: the catalogue is too large for a queue job.', ['active_products' => $products, 'limit' => $limit]);
                $this->tellAdmins(
                    'Sitemap not rebuilt from the queue',
                    number_format($products).' active parts is more than a queue job can finish in its one-hour limit, and stopping half way would leave a broken sitemap. The nightly schedule builds it with no time limit; to build it now, run "php artisan sitemap:generate" on the server (it takes about an hour).',
                    'heroicon-o-clock',
                    'warning',
                );

                return;
            }
        }

        $files = $sitemaps->generateAll();

        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Sitemap regenerated')
                    ->viewData(['push_topic' => 'task_completed'])
                    ->body(count($files).' file(s) written, requested by '.$this->triggeredBy.'.')
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('success')
            );
        } catch (Throwable $e) {
            // A bell notification must never fail a job that otherwise succeeded.
        }
    }

    /** A bell message to the admins; it must never fail the job. */
    private function tellAdmins(string $title, string $body, string $icon, string $color): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title($title)
                    ->viewData(['push_topic' => 'task_failed'])
                    ->body($body)
                    ->icon($icon)
                    ->iconColor($color)
            );
        } catch (Throwable $e) {
            // A bell notification must never mask what the job did.
        }
    }

    public function failed(Throwable $e): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Sitemap regeneration failed')
                    ->viewData(['push_topic' => 'task_failed'])
                    ->body($e->getMessage())
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }
}
