<?php

namespace App\Services\Migration\Import\Stages;

use App\Security\PackageAudit;
use App\Services\Backup\BackupLock;
use App\Services\Backup\LockCustody;
use App\Services\Instance\Contracts\InstanceBegin;
use App\Services\Migration\Import\ArmUndo;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\PackageIdentity;
use App\Services\Migration\Import\Recovery\RecoveryPoint;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Support\Facades\Log;

/**
 * Stage 6. Closes the site. It changes the ENVIRONMENT only (a lock, a marker, a pause, .env keys); the first statement
 * that touches DATA is the next stage's. Seven sub-steps in a fixed order, each its own flag, saved as it is done, so a
 * resume never repeats one:
 *
 *   lock      the shared backup/update/restore lock, owned by this run (busy: wait, nothing was touched);
 *   identity  the package file is still the one that was checked, and the site still holds what the way back holds;
 *   audit     the durable `import.started` record in files that survive the database being replaced;
 *   marker    from now the site answers 503 and the queue guard is armed (a single file, independent of any cache prefix);
 *   pause     the queue workers are held;
 *   begin     the instance mode for the run (through InstanceBegin, written to .env);
 *   repause   the workers again: the pause keys of the first step lived under the OLD cache prefix.
 *
 * The marker comes first and the prefix change later, so no moment exists in which a restarted worker is neither held
 * nor paused. A failure anywhere runs ArmUndo (reverse order) and ends the run `failed`: no data was touched.
 */
final class ArmStage implements ImportStage
{
    public function __construct(
        private readonly BackupLock $lock,
        private readonly ImportMarker $marker,
        private readonly QueueGuard $guard,
        private readonly InstanceBegin $begin,
        private readonly PackageAudit $audit,
        private readonly ImportStateStore $store,
        private readonly ArmUndo $undo,
    ) {}

    public function key(): string
    {
        return 'arm';
    }

    public function destructive(): bool
    {
        return false;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('arm.repause')) {
            return ImportStepResult::done('The site is closed.');
        }

        try {
            return $this->arm($state);
        } catch (\Throwable $e) {
            try {
                $this->undo->run($state);
            } catch (\Throwable $undoFailure) {
                // Never hide the original reason behind a failed undo; both are logged, and a resume finishes the undo from its flags.
                Log::channel('migration')->critical("Import {$state->id}: undoing the arm stage failed too: ".$undoFailure->getMessage());
            }

            throw $e;
        }
    }

    private function arm(ImportState $state): ImportStepResult
    {
        $owner = 'import:'.$state->id;

        if (! $state->flag('arm.lock')) {
            if ($this->lock->ensureHeld($owner) === LockCustody::Lost) {
                return ImportStepResult::awaitConfirmation('A backup, update or restore is running. The import starts when it is finished: ask again in a minute.');
            }

            $this->done($state, 'arm.lock');
        }

        if (! $state->flag('arm.identity')) {
            PackageIdentity::assertUnchanged((string) ($state->package['path'] ?? ''), (array) ($state->package['identity'] ?? []));
            $this->assertSiteUnchanged($state);
            $this->done($state, 'arm.identity');
        }

        if (! $state->flag('arm.audit')) {
            $this->audit->record('import.started', $state->id, [
                'admin_id' => $state->initiator['admin_id'] ?? null, 'email' => $state->initiator['email'] ?? null, 'ip' => $state->initiator['ip'] ?? null,
                'fingerprint' => $state->package['fingerprint'] ?? null, 'groups' => $state->package['groups'] ?? [],
                'launch' => $state->options['launch'] ?? 'live', 'door' => $state->options['door'] ?? 'cli',
            ]);
            $this->done($state, 'arm.audit');
        }

        if (! $state->flag('arm.marker')) {
            $this->marker->write($state->id);
            $this->done($state, 'arm.marker');
        }

        if (! $state->flag('arm.pause')) {
            $this->guard->hold();
            $this->done($state, 'arm.pause');
        }

        if (! $state->flag('arm.begin')) {
            $this->begin->begin($state);
            $this->done($state, 'arm.begin');
        }

        if (! $state->flag('arm.repause')) {
            $this->guard->hold();
            $this->done($state, 'arm.repause');
        }

        return ImportStepResult::done('The site is closed.');
    }

    /**
     * The way back was made some time ago. If the site gained or lost rows since, rolling back to it would lose them, so
     * the run stops here, while nothing has been touched, and has to be started again.
     */
    private function assertSiteUnchanged(ImportState $state): void
    {
        $drift = RecoveryPoint::of($state->id, $this->store)->drift();

        if ($drift !== []) {
            throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'site_changed', 'tables' => implode(',', array_slice(array_keys($drift), 0, 5))]);
        }
    }

    private function done(ImportState $state, string $flag): void
    {
        $state->setFlag($flag);
        $this->store->save($state);
    }
}
