<?php

namespace App\Services\Migration\Import\Stages;

use App\Models\ActivityLog;
use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Install\InstallManager;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\ImportStepResult;

/**
 * Stage 28. The last one: it lets go of everything the import held, in an order that never leaves a window:
 *
 *   1 scrubbed         secrets that were only needed to run (the extra administrator's hash) are removed from the run's state;
 *   2 escrow           the key of the package is deleted (the rollback needs it no more);
 *   3 marker           the import marker is removed: the 503 page ends and the queue workers are released;
 *   4 lock             the shared backup/update/restore lock is released;
 *   5 history          the durable `import.completed` line;
 *   6 activity         ONE Activity Log row for the admin panel;
 *   7 installed_lock   on the installer door only, and strictly last, `installed.lock` is written (an admin or command-line site
 *                      has one already). So it is never written while the marker exists.
 *
 * Every step has its own flag, saved as it is done, so a process killed anywhere carries on from there and produces one history
 * line, one Activity Log row and one `installed.lock`. A run whose marker is already gone but whose last stage is unfinished is
 * still resumed (by the command line, the runner page and the watchdog); that is why this stage does not demand the marker.
 */
final class CompleteStage extends FinalisingStage
{
    public function __construct(
        ImportMarker $marker,
        private readonly ImportStateStore $store,
        private readonly BackupLock $lock,
        private readonly PackageAudit $audit,
        private readonly InstallManager $installer,
    ) {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'complete';
    }

    protected function after(): string
    {
        return 'fin.readiness.done';
    }

    protected function flag(): string
    {
        return 'complete.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The import is complete.');
        }

        // Once the marker is gone this stage is being resumed after a kill; before that the site must still be closed.
        if (! $state->flag('complete.marker')) {
            $this->guard($state);
        }

        $this->once($state, 'complete.scrubbed', function () use ($state): void {
            unset($state->options['extra_admin']['password_hash']);
        });

        $this->once($state, 'complete.escrow', function () use ($state, $context): void {
            $context->escrow->delete(ImportContext::escrowId($state->id));
            $state->escrowId = null;
        });

        $this->once($state, 'complete.marker', fn () => $this->marker->remove());
        $this->once($state, 'complete.lock', fn () => $this->lock->releaseIfOwnedBy('import:'.$state->id));
        $this->once($state, 'complete.history', fn () => $this->audit->record('import.completed', $state->id, $this->summary($state)));
        $this->once($state, 'complete.activity', fn () => $this->activity($state));
        $this->once($state, 'complete.installed_lock', function () use ($state): void {
            if (($state->options['door'] ?? '') === 'installer') {
                $this->installer->finalizeLock();
            }
        });

        $state->status = ImportStatus::Complete;
        $state->finishedAt ??= now()->toIso8601String();
        $state->setFlag($this->flag());

        return ImportStepResult::done('The import is complete.');
    }

    /** Do one sub-step, then say so in the state on disk before the next begins. */
    private function once(ImportState $state, string $flag, \Closure $do): void
    {
        if ($state->flag($flag)) {
            return;
        }

        $do();
        $state->setFlag($flag);
        $state->touch();
        $this->store->save($state);
    }

    private function activity(ImportState $state): void
    {
        $exists = ActivityLog::query()->where('action', 'import.completed')->where('model_type', PackageAudit::class)->where('new_values->run_id', $state->id)->exists();

        if (! $exists) {
            $this->audit->recordActivityLog('import.completed', $state->id, isset($state->initiator['admin_id']) ? (int) $state->initiator['admin_id'] : null, $this->summary($state));
        }
    }

    /** @return array<string, mixed> counts only */
    private function summary(ImportState $state): array
    {
        return [
            'launch' => (string) ($state->options['launch'] ?? 'live'),
            'door' => (string) ($state->options['door'] ?? 'cli'),
            'groups' => array_values((array) ($state->package['groups'] ?? [])),
            'rows' => array_sum(array_map('intval', (array) ($state->package['loaded'] ?? []))),
            'files' => (int) ($state->package['files']['written'] ?? 0),
        ];
    }
}
