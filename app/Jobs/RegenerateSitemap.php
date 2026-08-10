<?php

namespace App\Jobs;

use App\Services\SitemapService;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Queued wrapper around SitemapService::generateAll(), used by the "Regenerate
 * sitemap now" action on SeoSettings instead of calling the service inline.
 * At ~100k products (500k+ <loc> entries once split across locales),
 * generation can run long enough to risk the web request's timeout. On an
 * install with a real queue worker this defers the work off the request; on
 * a QUEUE_CONNECTION=sync install (shared hosting, rule #41 — no worker
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

    public int $tries = 2;

    public array $backoff = [30];

    public int $timeout = 600;

    public function __construct(
        public string $triggeredBy,
    ) {
        $this->onQueue('default');
    }

    public function handle(SitemapService $sitemaps): void
    {
        $files = $sitemaps->generateAll();

        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Sitemap regenerated')
                    ->body(count($files).' file(s) written, requested by '.$this->triggeredBy.'.')
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('success')
            );
        } catch (Throwable $e) {
            // A bell notification must never fail a job that otherwise succeeded.
        }
    }

    public function failed(Throwable $e): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Sitemap regeneration failed')
                    ->body($e->getMessage())
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }
}
