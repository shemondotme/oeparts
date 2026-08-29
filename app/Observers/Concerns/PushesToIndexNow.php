<?php

namespace App\Observers\Concerns;

use App\Jobs\PushIndexNow;

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

            PushIndexNow::dispatch($urls);
        } catch (\Throwable $e) {
            // Must not break the save that triggered this.
        }
    }
}
