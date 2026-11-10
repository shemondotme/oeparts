<?php

namespace App\Observers\Concerns;

use App\Jobs\PushIndexNow;
use App\Services\SettingsService;
use App\Support\PublicBaseUrl;
use Illuminate\Support\Facades\Log;

/**
 * Shared by every content observer that announces URL changes to IndexNow
 * (Bing/Yandex — not Google, which doesn't consume this protocol). Kept as
 * one trait rather than duplicating the same enabled-check/dispatch/catch
 * block across each observer.
 */
trait PushesToIndexNow
{
    /**
     * PushIndexNow itself no-ops when disabled/unkeyed — this check is
     * just to avoid building URLs and dispatching a job for nothing on
     * every single write when the feature isn't in use at all.
     */
    protected function pushToIndexNow(array $urls): void
    {
        try {
            if ($urls === [] || ! filter_var(settings('seo.indexnow_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            // These URLs are built with route()/url() where the change
            // happened — often a queue worker or importer, where that is
            // APP_URL. If it is still a local address, re-root them onto the
            // real public address here rather than letting every push be
            // refused (or sent with the wrong host).
            $settings = app(SettingsService::class);
            $urls = array_map(fn (string $url) => PublicBaseUrl::rewrite($url, $settings), $urls);

            PushIndexNow::dispatch($urls);
        } catch (\Throwable $e) {
            // Must not break the save that triggered this.
            Log::warning('PushesToIndexNow::pushToIndexNow() failed to dispatch: '.$e->getMessage());
        }
    }
}
