<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationErrorCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Clears what package downloads leave behind: a download nobody has touched for `abandoned_hours` is closed (its bytes freed:
 * a half-downloaded 30 GB file must not sit on the disk forever), and the record of a finished one is removed after
 * `retention_days`. A transfer that is being driven right now is never touched: it holds its lock.
 */
final class TransferJanitor
{
    public function __construct(private readonly TransferStore $store) {}

    /** @return array{abandoned: int, removed: int} */
    public function run(): array
    {
        $abandoned = 0;
        $removed = 0;
        $abandonedBefore = Carbon::now()->subHours(max(1, (int) config('migration.transport.abandoned_hours', 48)));
        $removedBefore = Carbon::now()->subDays(max(1, (int) config('migration.transport.retention_days', 7)));

        foreach ($this->store->all() as $state) {
            $lock = $this->store->lock($state->id);

            if ($lock === null) {
                continue; // somebody is driving it
            }

            try {
                $touched = Carbon::parse($state->updatedAt !== '' ? $state->updatedAt : $state->createdAt);

                if (! $state->status->isTerminal() && $touched->lt($abandonedBefore)) {
                    $this->store->discardPart($state->id);
                    $this->store->update($state->id, static function (TransferState $s): void {
                        $s->status = TransferStatus::Failed;
                        $s->finishedAt = Carbon::now()->toIso8601String();
                        $s->claimEncrypted = null;
                        $s->hashContext = null;
                        $s->error = ['code' => MigrationErrorCode::UrlTimeout->value, 'params' => ['reason' => 'abandoned']];
                        $s->message = 'Nobody carried this download on; it was closed.';
                    });
                    Log::info('migration: an abandoned download was closed', ['transfer' => $state->id]);
                    $abandoned++;
                } elseif ($state->status->isTerminal() && $touched->lt($removedBefore)) {
                    $this->store->unlock($lock);
                    $lock = null;
                    $this->store->delete($state->id);
                    $removed++;
                }
            } finally {
                if ($lock !== null) {
                    $this->store->unlock($lock);
                }
            }
        }

        return ['abandoned' => $abandoned, 'removed' => $removed];
    }
}
