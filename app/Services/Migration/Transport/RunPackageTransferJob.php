<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

/**
 * Drives a package download from a queue worker, ONE budgeted advance at a time, then puts itself back on the queue while the
 * transfer is not over (after the pause, when it is paused). It is dispatched only when a worker is PROVEN to serve the
 * migration queue (TransferManager::kick); otherwise a poll or the command line drives the transfer.
 *
 * `tries = 1`: the transfer's own state machine decides what a failure means; a job that dies is simply started again by
 * the next poll, because the state and the bytes are on disk. `timeout` is generous: one request can take its full
 * five-minute limit on top of the budget.
 */
class RunPackageTransferJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $transferId)
    {
        $this->onQueue(MigrationQueues::migration());
    }

    public function handle(TransferManager $manager, TransferLimits $limits): void
    {
        $state = $manager->find($this->transferId);

        if ($state === null || $state->status->isTerminal()) {
            return;
        }

        $state = $manager->advance($this->transferId, $limits->jobBudgetSeconds());

        if ($state->status->isTerminal()) {
            return;
        }

        $wait = $state->status === TransferStatus::Paused && $state->nextAttemptAt !== null
            ? max(1, (int) Carbon::now()->diffInSeconds(Carbon::parse($state->nextAttemptAt), false))
            : 1;

        self::dispatch($this->transferId)->delay(now()->addSeconds($wait));
    }

    public function failed(\Throwable $e): void
    {
        $manager = app(TransferManager::class);
        $state = $manager->find($this->transferId);

        if ($state !== null && ! $state->status->isTerminal()) {
            $manager->store()->update($this->transferId, static function (TransferState $s) use ($e): void {
                $s->status = TransferStatus::Failed;
                $s->finishedAt = Carbon::now()->toIso8601String();
                $s->error = ['code' => MigrationErrorCode::UrlHttpError->value, 'params' => ['status' => 0, 'reason' => 'internal']];
                $s->message = 'The download job crashed: '.$e->getMessage();
            });
        }
    }
}
