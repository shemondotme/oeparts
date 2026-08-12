<?php

namespace App\Jobs;

use App\Models\IndexNowPushLog;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pushes a batch of URLs to the IndexNow protocol (api.indexnow.org),
 * consumed by Bing/Yandex/Naver/Seznam for near-instant re-crawl — NOT
 * Google, which does not support IndexNow (a common misconception; Google
 * still relies on sitemaps + Search Console).
 *
 * Shaped exactly like RegenerateSitemap: queued, bounded tries/backoff/
 * timeout, non-fatal bell notification on success/failure. Takes a batch
 * (not one job per URL) — IndexNow's API accepts up to 10,000 URLs per
 * call, so a bulk product import dispatches one job per product save
 * rather than flooding the queue with thousands of single-URL jobs would
 * require batching upstream; this job itself just posts whatever list
 * it's given in one request.
 */
class PushIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public function __construct(
        public array $urls,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (! filter_var(settings('seo.indexnow_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $key = trim((string) settings('seo.indexnow_api_key', ''));
        if ($key === '' || empty($this->urls)) {
            return;
        }

        $host = parse_url(url('/'), PHP_URL_HOST);

        $response = Http::timeout($this->timeout)->post('https://api.indexnow.org/indexnow', [
            'host' => $host,
            'key' => $key,
            'urlList' => array_values($this->urls),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("IndexNow push failed with status {$response->status()}: {$response->body()}");
        }

        $this->recordPush('success');

        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('IndexNow push succeeded')
                    ->body(count($this->urls).' URL(s) submitted.')
                    ->icon('heroicon-o-bolt')
                    ->iconColor('success')
            );
        } catch (Throwable $e) {
            // A bell notification must never fail a job that otherwise succeeded.
        }
    }

    public function failed(Throwable $e): void
    {
        $this->recordPush('failed', $e->getMessage());

        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('IndexNow push failed')
                    ->body($e->getMessage())
                    ->icon('heroicon-o-bolt')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }

    /**
     * Best-effort activity log for the SEO Health Dashboard's IndexNow
     * widget — a logging failure must never mask (or throw over) the
     * actual push outcome, which has already happened by the time this runs.
     */
    private function recordPush(string $status, ?string $errorMessage = null): void
    {
        try {
            IndexNowPushLog::create([
                'url_count' => count($this->urls),
                'status' => $status,
                'error_message' => $errorMessage,
            ]);
        } catch (Throwable $e) {
            // Swallowed — see docblock.
        }
    }
}
