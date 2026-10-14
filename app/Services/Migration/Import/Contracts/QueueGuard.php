<?php

namespace App\Services\Migration\Import\Contracts;

/**
 * Holds the queue workers still while an import replaces the data they would write to, and lets them go again.
 *
 * `hold()` is applied when the site is closed, `resume()` when the import ends (done, undone or rolled back). Until the
 * real guard exists (it holds every worker that serves a customer queue, even if the cache is flushed) the application
 * binds NullQueueGuard, which does nothing.
 */
interface QueueGuard
{
    public function hold(): void;

    public function resume(): void;
}
