<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use Illuminate\Support\Facades\Artisan;

/**
 * Stage 25 (a failure is a warning). The queue workers are told to restart (they read .env once, when they started) and the
 * pauses put on the customer queues when the site was closed are lifted. The workers are still held by the import marker, which
 * the last stage removes: only then do they take a job.
 */
final class FinQueueStage extends FinalisingStage
{
    public function __construct(ImportMarker $marker, private readonly QueueGuard $queues)
    {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_queue';
    }

    protected function after(): string
    {
        return 'fin.sitemap.done';
    }

    protected function flag(): string
    {
        return 'fin.queue.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The workers are released.');
        }

        $this->guard($state);

        if (config('queue.default') !== 'sync') {
            try {
                Artisan::call('queue:restart');
            } catch (\Throwable $e) {
                $this->warnFailure($state, 'The queue workers could not be told to restart. Restart them on the server.', $e);
            }
        }

        try {
            $this->queues->resume();
        } catch (\Throwable $e) {
            $this->warnFailure($state, 'The paused queues could not be released.', $e);
        }

        $state->setFlag($this->flag());

        return ImportStepResult::done('The workers are released.');
    }
}
