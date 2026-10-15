<?php

namespace App\Services\Migration\Import;

use App\Services\Backup\BackupLock;
use App\Services\Backup\LockCustody;
use App\Services\Instance\InstanceState;
use App\Services\Migration\Import\Contracts\QueueGuard;
use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\Recovery\RecoveryPoint;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Drives an import run one budgeted step at a time: the engine behind the runner page, the command line and the
 * watchdog (all three call advance()). It holds no state of its own: everything is in the run's state file, so any
 * process can pick the run up where the last one left it.
 *
 * Only one process advances a run at a time (a kernel flock; the loser reports "busy" and tries again at its next
 * poll). Each step: take the run, stamp the heartbeat, let the current stage do a bounded piece of work, save, and
 * move on when the stage says it is done. A stage that throws a MigrationException about the PASSPHRASE leaves the run
 * where it is so a person can try again; any other failure ends a run that has not touched the site as `failed`, and
 * (from the first destructive stage on) hands it to the rollback.
 */
class ImportManager implements RunDriver
{
    /** The client key of the drivers nobody is watching (the watchdog, the self-drive of the 503 gate). */
    public const BACKGROUND = 'background';

    /** Errors that mean "ask the person again", not "the run is dead". */
    private const INPUT_ERRORS = [
        MigrationErrorCode::PassphraseEmpty,
        MigrationErrorCode::PassphraseWrong,
        MigrationErrorCode::PassphraseLocked,
    ];

    public function __construct(
        private readonly ImportStateStore $store,
        private readonly ImportRunMutex $mutex,
        private readonly ImportStageRegistry $stages,
        private readonly KeyEscrow $escrow,
        private readonly ImportInbox $inbox,
        private readonly BackupLock $lock,
        private readonly TargetFreshness $freshness,
        private readonly ImportRollback $rollback,
        private readonly ImportReportBuilder $reports,
        private readonly ImportMarker $marker,
        private readonly QueueGuard $queues,
        private readonly InstanceState $ledger,
    ) {}

    /**
     * Begin a run for a package and take its first step (finding the package and opening it with the passphrase).
     *
     * @param  array{path?: string, inbox?: string}  $source  an absolute readable path (the command line) or a file name in the inbox (the web)
     * @param  array<string, mixed>  $initiator  who is doing this: admin id, email, address
     * @param  array<string, mixed>  $options  door (cli|admin|installer), launch (live|staging), groups, include_secrets, extra_admin
     * @return array{state: ImportState, token: string, progress: array<string, mixed>} the runner token is returned ONCE, only its hash is kept
     *
     * @throws MigrationException the package cannot be found, or is not a package
     */
    public function start(array $source, #[\SensitiveParameter] ?string $passphrase, array $initiator = [], string $kind = 'import', string $clientKey = 'cli', array $options = []): array
    {
        [$path, $origin] = $this->resolveSource($source);

        $token = bin2hex(random_bytes(32));
        $state = $this->store->create($kind, [
            'package' => ['path' => $path, 'origin' => $origin],
            'initiator' => $initiator,
            'options' => $options,
            'token_hash' => hash('sha256', $token),
            'stage' => $this->stages->keys($kind)[0] ?? 'locate',
        ]);

        $progress = $this->advance($state->id, 0.0, $passphrase, $clientKey);

        return ['state' => $this->store->find($state->id) ?? $state, 'token' => $token, 'progress' => $progress];
    }

    public function advance(string $runId, float $budgetSeconds, #[\SensitiveParameter] ?string $passphrase = null, string $clientKey = 'cli'): array
    {
        $handle = $this->mutex->acquire($runId);

        if ($handle === null) {
            return $this->status($runId) + ['busy' => true];
        }

        try {
            return $this->advanceLocked($runId, $budgetSeconds, $passphrase, $clientKey);
        } finally {
            $this->mutex->release($handle);
        }
    }

    /** Type the passphrase of a run that is waiting for it (its key expired, or the first try was wrong). */
    public function provideSecret(string $runId, #[\SensitiveParameter] string $passphrase, string $clientKey = 'cli'): array
    {
        return $this->advance($runId, 0.0, $passphrase, $clientKey);
    }

    /**
     * Say "go": the one deliberate human act between "everything was checked" and "the site is replaced".
     *
     * Nothing is changed here. It re-checks what could have changed since the checks ran (the file, the lock, what the
     * site holds), demands every acknowledgement the checks asked for and the typed REPLACE where the door needs it,
     * freezes the options, and lets the run go on to the stages that change things.
     *
     * @param  array<int, string>  $acks  the acknowledgement keys the person gave
     * @param  array{door?: string, typed?: string|null, replace_populated?: bool, options?: array<string, mixed>}  $confirmation
     *
     * @throws MigrationException not ready, a failed check, a missing acknowledgement, a missing REPLACE, a package or site that changed
     */
    public function confirm(string $runId, array $acks, array $confirmation = []): ImportState
    {
        if ($this->store->find($runId) === null) {
            throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);
        }

        $handle = $this->mutex->acquire($runId);

        if ($handle === null) {
            throw new MigrationException(MigrationErrorCode::LockBusy);
        }

        try {
            $state = $this->store->find($runId);

            if ($state->status !== ImportStatus::AwaitingConfirmation || ! $state->flag('preflight.done') || $state->kind !== 'import') {
                throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);
            }

            $preflight = (array) ($state->package['preflight'] ?? []);

            if (($preflight['can_proceed'] ?? false) !== true) {
                throw new MigrationException(MigrationErrorCode::PreflightFailed);
            }

            foreach ((array) ($preflight['required_acks'] ?? []) as $needed) {
                if (! in_array($needed, $acks, true)) {
                    throw new MigrationException(MigrationErrorCode::AcknowledgementMissing, ['what' => $needed]);
                }
            }

            $door = (string) ($confirmation['door'] ?? ($state->options['door'] ?? 'cli'));
            $populated = $this->targetClass($preflight) === TargetClass::Populated;

            if ($door === 'admin' && ($confirmation['typed'] ?? null) !== 'REPLACE') {
                throw new MigrationException(MigrationErrorCode::ReplaceNotConfirmed);
            }

            if ($door === 'cli' && (($confirmation['typed'] ?? null) !== 'REPLACE' || ($populated && ($confirmation['replace_populated'] ?? false) !== true))) {
                throw new MigrationException(MigrationErrorCode::ReplaceNotConfirmed);
            }

            // What could have changed since the checks ran. The file first: a swapped package must not be imported on the strength of a check made of another.
            PackageIdentity::assertUnchanged((string) ($state->package['path'] ?? ''), (array) ($state->package['identity'] ?? []));

            if ($this->lock->isLocked() && ($this->lock->owner()['owner'] ?? '') !== 'import:'.$runId) {
                throw new MigrationException(MigrationErrorCode::LockBusy, ['owner' => (string) ($this->lock->owner()['owner'] ?? '')]);
            }

            if ($this->freshness->assess()->class !== $this->targetClass($preflight)) {
                // The site gained or lost business records since the checks: what the person agreed to is no longer what would happen.
                throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'target_changed']);
            }

            $state->options = array_replace($state->options, (array) ($confirmation['options'] ?? []), ['door' => $door, 'acks' => array_values($acks), 'confirmed_at' => now()->toIso8601String()]);
            $state->setFlag('confirmed');
            $state->status = ImportStatus::Running;
            $state->touch();

            // The stages that change things join the registry in later steps; until the next one exists the run stays where it is.
            $next = $this->stages->nextAfter($state->stage, $state->kind);

            if ($next !== null) {
                $state->stage = $next;
                $state->stageState = [];
            }

            $this->store->save($state);

            return $state;
        } finally {
            $this->mutex->release($handle);
        }
    }

    /** Run the checks again (after the person fixed something). Only before confirmation. */
    public function rerunPreflight(string $runId): array
    {
        if ($this->store->find($runId) === null) {
            throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);
        }

        $handle = $this->mutex->acquire($runId);

        if ($handle === null) {
            return $this->status($runId) + ['busy' => true];
        }

        try {
            $state = $this->store->find($runId);

            if ($state->flag('confirmed') || $state->status->isTerminal()) {
                throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);
            }

            $state->flags['preflight.done'] = false;
            $state->status = ImportStatus::Running;
            $state->stage = 'preflight';
            $state->stageState = [];
            $this->store->save($state);
        } finally {
            $this->mutex->release($handle);
        }

        return $this->advance($runId, 0.0);
    }

    public function status(string $runId): array
    {
        $state = $this->store->find($runId);

        if ($state === null) {
            return ['found' => false];
        }

        $kind = $state->kind;
        $fraction = (float) ($state->stageState['_fraction'] ?? 0.0);

        return [
            'found' => true,
            'id' => $state->id,
            'kind' => $kind,
            'status' => $state->status->value,
            'stage' => $state->stage,
            'terminal' => $state->status->isTerminal(),
            'needs_attention' => $state->status->needsAttention(),
            'needs_passphrase' => $state->flag('needs_passphrase'),
            'stalled' => is_file($this->store->directory($runId).DIRECTORY_SEPARATOR.'stalled.flag'),
            'destructive' => $state->isDestructive(),
            'heartbeat_at' => $state->heartbeatAt,
            'percent' => $state->status === ImportStatus::Complete ? 100.0 : $this->stages->percent($state->stage, $fraction, $kind),
            'detail' => (string) ($state->stageState['_message'] ?? ''),
            'error' => $state->error,
        ];
    }

    /**
     * Remove a run that never changed anything (and its stored key). Refused once the first destructive statement ran.
     */
    public function discard(string $runId): void
    {
        $state = $this->store->find($runId);

        if ($state === null) {
            return;
        }

        if ($state->isDestructive()) {
            throw new \LogicException('A run that has changed the site cannot simply be discarded: roll it back.');
        }

        $this->escrow->delete(ImportContext::escrowId($runId));
        File::deleteDirectory($this->store->directory($runId));
    }

    // ---------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function advanceLocked(string $runId, float $budgetSeconds, #[\SensitiveParameter] ?string $passphrase, string $clientKey): array
    {
        if ($clientKey !== self::BACKGROUND) {
            $this->forgetBackgroundTrouble($runId); // a person is looking at the run now: the attempts of the machines start over
        }

        $result = $this->advanceCore($runId, $budgetSeconds, $passphrase, $clientKey);
        $this->reportIfFinished($runId);

        return $result;
    }

    /**
     * One step by a driver nobody is watching. It is only taken when the run is due (the persisted backoff has passed and the
     * run is not stalled), and what came of it is written down under the run's own lock: a step that moved nothing counts as a
     * failed attempt and doubles the wait; after `import.self_drive.max_failures` of them the run is stalled and no machine
     * touches it again until a person does.
     *
     * @return array<string, mixed>
     */
    public function advanceInBackground(string $runId, float $budgetSeconds): array
    {
        $handle = $this->mutex->acquire($runId);

        if ($handle === null) {
            return $this->status($runId) + ['busy' => true, 'advanced' => false];
        }

        try {
            $state = $this->store->find($runId);

            if ($state === null || $state->status->isTerminal() || $state->flag('needs_passphrase')
                || ($state->status === ImportStatus::AwaitingConfirmation && ! $state->flag('confirmed'))) {
                return $this->status($runId) + ['advanced' => false];
            }

            if ($this->isStalled($runId) || $state->consecutiveFailures >= $this->maxFailures()) {
                return $this->status($runId) + ['advanced' => false, 'stalled' => true];
            }

            if ($state->nextSelfDriveAt !== null && now()->lt(Carbon::parse($state->nextSelfDriveAt))) {
                return $this->status($runId) + ['advanced' => false, 'backoff' => true];
            }

            $before = $this->signature($state);
            $result = $this->advanceLocked($runId, $budgetSeconds, null, self::BACKGROUND);
            $after = $this->store->find($runId);

            if ($after !== null) {
                $this->account($after, $before);
            }

            return $this->status($runId) + ['advanced' => ($result['advanced'] ?? false) === true];
        } finally {
            $this->mutex->release($handle);
        }
    }

    /** @return array<string, mixed> */
    private function advanceCore(string $runId, float $budgetSeconds, #[\SensitiveParameter] ?string $passphrase, string $clientKey): array
    {
        $state = $this->store->find($runId);

        // A run waiting for a confirmation that has not been given does nothing. A run that WAS confirmed and is waiting again
        // (the arm stage found the shared lock busy) tries again every time it is asked.
        if ($state === null || $state->status->isTerminal() || ($state->status === ImportStatus::AwaitingConfirmation && ! $state->flag('confirmed'))) {
            return $this->status($runId) + ['advanced' => false];
        }

        if ($state->status === ImportStatus::AwaitingConfirmation) {
            $state->status = ImportStatus::Running;
        }

        $deadline = microtime(true) + max(0.0, $budgetSeconds);
        $context = new ImportContext($deadline, $this->escrow, $passphrase, $clientKey);
        $passphrase = null; // from here on only the context holds it, for this call

        if ($state->status === ImportStatus::RollingBack) {
            return $this->rollBack($state, $context);
        }

        // A run past its first stage whose stored key is gone (it expired) takes the passphrase the person just typed
        // back through the locate stage, which checks it opens the SAME package, then carries on where it was.
        $rekey = $context->passphrase !== null && $state->flag('locate.done') && $state->stage !== 'locate'
            && $this->escrow->get(ImportContext::escrowId($state->id)) === null;

        do {
            $stage = $this->stages->get($rekey ? 'locate' : $state->stage);

            if ($stage === null) {
                $this->fail($state, new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => $state->stage]));

                break;
            }

            $state->touch();

            $stepStarted = microtime(true);

            try {
                $result = $stage->step($state, $context);
                $state->stageSeconds[$stage->key()] = round(($state->stageSeconds[$stage->key()] ?? 0.0) + microtime(true) - $stepStarted, 2);
            } catch (MigrationException $e) {
                if (in_array($e->errorCode, self::INPUT_ERRORS, true)) {
                    $state->setFlag('needs_passphrase');
                    $state->stageState['_message'] = $e->errorCode->value;
                    $this->store->save($state);

                    return $this->status($runId) + ['advanced' => false, 'input_error' => $e->errorCode->value, 'input_params' => $e->params];
                }

                $this->fail($state, $e);

                break;
            } catch (\Throwable $e) {
                $this->fail($state, $e);

                break;
            }

            if ($result->needsPassphrase) {
                $state->setFlag('needs_passphrase');
                $state->stageState['_message'] = $result->message;
                $this->store->save($state);

                return $this->status($runId) + ['advanced' => false];
            }

            if ($rekey) {
                $rekey = false;
                $state->flags['needs_passphrase'] = false;
                $this->store->save($state);

                continue;
            }

            $state->flags['needs_passphrase'] = false;
            $state->stageState['_fraction'] = $result->fraction;
            $state->stageState['_message'] = $result->message;

            if ($result->awaitConfirmation) {
                $state->status = ImportStatus::AwaitingConfirmation;
                $this->store->save($state);

                break;
            }

            if ($result->done) {
                $this->moveOn($state);
            }

            $this->store->save($state);

            if ($state->status->isTerminal() || $state->status === ImportStatus::AwaitingConfirmation) {
                break;
            }
        } while (! $context->expired() && $budgetSeconds > 0.0);

        return $this->status($runId) + ['advanced' => true];
    }

    /**
     * Put the site back. Keeps stepping until the budget is spent, saving after every step, so a killed process resumes.
     *
     * @return array<string, mixed>
     */
    private function rollBack(ImportState $state, ImportContext $context): array
    {
        do {
            $state->touch();
            $ended = $this->rollback->step($state, $context);
            $this->store->save($state);
        } while (! $ended && ! $context->expired());

        return $this->status($state->id) + ['advanced' => true];
    }

    /**
     * Undo an import that stopped after it started changing the site, whose rollback failed and is to be tried again, or that
     * COMPLETED and is to be taken back. A run that has not changed anything has nothing to roll back: discard it instead.
     *
     * Taking back a completed import closes the site again first (lock, marker, queue hold), and refuses when the site has been
     * used since the import (an order, a customer, a payment above the ids the import ended with): rolling back would throw that
     * away. A person who has looked at that and accepts the loss passes `$acceptLoss`.
     *
     * @return array<string, mixed>
     */
    public function rollbackRun(string $runId, float $budgetSeconds = 10.0, bool $acceptLoss = false): array
    {
        $handle = $this->mutex->acquire($runId);

        if ($handle === null) {
            return $this->status($runId) + ['busy' => true];
        }

        try {
            $state = $this->store->find($runId);

            if ($state === null || ! in_array($state->status, [ImportStatus::RollbackFailed, ImportStatus::RollingBack, ImportStatus::Running, ImportStatus::AwaitingConfirmation, ImportStatus::Complete], true) || ! $state->isDestructive()) {
                throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'nothing_to_roll_back']);
            }

            if ($state->status === ImportStatus::Complete) {
                $this->reclose($state, $acceptLoss);
            }

            $state->status = ImportStatus::RollingBack;
            $state->error = null;
            $this->store->save($state);

            $progress = $this->rollBack($state, new ImportContext(microtime(true) + max(0.0, $budgetSeconds), $this->escrow));
            $this->reportIfFinished($runId);

            return $progress;
        } finally {
            $this->mutex->release($handle);
        }
    }

    /**
     * Close the site again so a COMPLETED import can be taken back: nothing may have been written since (unless the person accepts
     * losing it), the way back must still exist, and the shared lock must be free.
     */
    private function reclose(ImportState $state, bool $acceptLoss): void
    {
        if (! RecoveryPoint::of($state->id, $this->store)->isComplete()) {
            throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'no_recovery_point']);
        }

        if (! $acceptLoss) {
            $since = $this->activitySinceImport();

            if ($since !== []) {
                throw new MigrationException(MigrationErrorCode::NotReadyToConfirm, ['what' => 'activity_since_import', 'tables' => implode(',', $since)]);
            }
        }

        $owner = 'import:'.$state->id;

        if ($this->lock->ensureHeld($owner) === LockCustody::Lost) {
            throw new MigrationException(MigrationErrorCode::LockBusy, ['owner' => (string) ($this->lock->owner()['owner'] ?? '')]);
        }

        $this->marker->write($state->id);
        $this->queues->hold();

        $state->finishedAt = null;
        $state->flags['complete.done'] = false;
    }

    /** @return array<int, string> the business tables that hold rows above the ids the import ended with */
    private function activitySinceImport(): array
    {
        $tables = [];

        foreach (TargetFreshness::BUSINESS_TABLES as $table) {
            $watermark = $this->ledger->watermark($table);

            if ($watermark !== null && (int) (DB::table($table)->max('id') ?? 0) > $watermark) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /** What counts as "this run moved": its status, its stage, how many parts are finished, and its cursor inside the stage. */
    private function signature(ImportState $state): string
    {
        return (string) json_encode([$state->status->value, $state->stage, count(array_filter($state->flags)), $state->stageState]);
    }

    /** After a background step: progress clears the record, no progress adds to it and lengthens the wait. */
    private function account(ImportState $after, string $before): void
    {
        $moved = $this->signature($after) !== $before || $after->status->isTerminal();
        $max = $this->maxFailures();

        $updated = $this->store->update($after->id, function (ImportState $state) use ($moved): void {
            if ($moved) {
                $state->consecutiveFailures = 0;
                $state->nextSelfDriveAt = null;

                return;
            }

            $state->consecutiveFailures++;
            $wait = min(
                (int) config('migration.import.self_drive.backoff_max_seconds', 900),
                (int) config('migration.import.self_drive.backoff_base_seconds', 30) * (2 ** max(0, $state->consecutiveFailures - 1)),
            );
            $state->nextSelfDriveAt = now()->addSeconds($wait)->toIso8601String();
        });

        if (! $moved && $updated->consecutiveFailures >= $max) {
            $this->markStalled($updated->id);
        }
    }

    private function maxFailures(): int
    {
        return max(1, (int) config('migration.import.self_drive.max_failures', 5));
    }

    public function isStalled(string $runId): bool
    {
        return is_file($this->store->directory($runId).DIRECTORY_SEPARATOR.'stalled.flag');
    }

    /** The 503 page and the runner say the move needs attention; only a machine that gave up (or the watchdog's age rule) writes this. */
    public function markStalled(string $runId): void
    {
        $file = $this->store->directory($runId).DIRECTORY_SEPARATOR.'stalled.flag';

        if (! is_file($file)) {
            @file_put_contents($file, now()->toIso8601String());
            Log::channel('migration')->warning("Import {$runId} is stalled: no progress, no driver will touch it until a person does.");
        }
    }

    private function forgetBackgroundTrouble(string $runId): void
    {
        $file = $this->store->directory($runId).DIRECTORY_SEPARATOR.'stalled.flag';

        if (is_file($file)) {
            @unlink($file);
        }

        $state = $this->store->find($runId);

        if ($state !== null && ($state->consecutiveFailures > 0 || $state->nextSelfDriveAt !== null) && ! $state->status->isTerminal()) {
            $this->store->update($runId, function (ImportState $s): void {
                $s->consecutiveFailures = 0;
                $s->nextSelfDriveAt = null;
            });
        }
    }

    /** A run that has ended gets its report written once; a failure to write it never hides how the run ended. */
    private function reportIfFinished(string $runId): void
    {
        try {
            $state = $this->store->find($runId);

            if ($state !== null && $state->status->isTerminal() && $this->reports->statusOnDisk($runId) !== $state->status->value) {
                $this->reports->write($state);
            }
        } catch (\Throwable $e) {
            Log::channel('migration')->warning("The report of import {$runId} could not be written: ".$e->getMessage());
        }
    }

    /** The stage is finished: next stage, or the end of the road for this kind of run. */
    private function moveOn(ImportState $state): void
    {
        $next = $this->stages->nextAfter($state->stage, $state->kind);

        if ($next !== null) {
            $state->stage = $next;
            $state->stageState = [];

            return;
        }

        // The last stage of a real run says itself that the run is complete (it sets the status and the time).
        if ($state->status === ImportStatus::Complete) {
            $state->stageState = ['_fraction' => 1.0, '_message' => ''];
            $state->finishedAt ??= now()->toIso8601String();

            return;
        }

        // No stage follows. A dry run is complete here. A real run waits for a person: the stages that carry it further
        // (preflight and everything after) join this list in later steps.
        $state->stageState = ['_fraction' => 1.0, '_message' => ''];
        $state->status = $state->kind === 'dry_run' ? ImportStatus::Complete : ImportStatus::AwaitingConfirmation;
        $state->finishedAt = $state->status->isTerminal() ? now()->toIso8601String() : null;
    }

    /**
     * A stage threw. Before any data was touched the run simply ends as `failed`, its key is dropped and the site is as
     * it was; from the first destructive stage on, the rollback takes over (it lands with the recovery point).
     */
    private function fail(ImportState $state, \Throwable $e): void
    {
        $code = $e instanceof MigrationException ? $e->errorCode : MigrationErrorCode::ImportFailedStep;
        $params = $e instanceof MigrationException ? $e->params : ['stage' => $state->stage];

        // The technical detail goes to the log (never a passphrase, a key or a setting value: stages never put one in a message).
        Log::channel('migration')->error("Import {$state->id} stopped in stage {$state->stage}: ".$e->getMessage());

        $state->error = ['code' => $code->value, 'params' => $params, 'stage' => $state->stage];

        // Once the import marker is gone the shop is live with every row loaded. A later closing sub-step that throws (the history
        // line, the Activity Log, the wizard file, the installed lock) must NEVER erase that: keep the run as it is and let the next
        // advance retry the closing steps (each one is idempotent and has its own flag).
        if ($state->flag('complete.marker')) {
            $state->error += ['closing' => true];
            $state->stageState['_message'] = 'The import finished. One closing step needs a retry.';
            $this->store->save($state);

            return;
        }

        if ($state->isDestructive()) {
            $state->status = ImportStatus::RollingBack;
        } else {
            $state->status = ImportStatus::Failed;
            $state->finishedAt = now()->toIso8601String();
            $this->escrow->delete(ImportContext::escrowId($state->id));
        }

        $this->store->save($state);
    }

    /** @param  array<string, mixed>  $preflight */
    private function targetClass(array $preflight): ?TargetClass
    {
        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            if (($check['key'] ?? '') === 'target_state') {
                return TargetClass::tryFrom((string) ($check['meta']['class'] ?? ''));
            }
        }

        return null;
    }

    /** @return array{0: string, 1: string} absolute path and where it came from */
    private function resolveSource(array $source): array
    {
        if (isset($source['inbox'])) {
            return [$this->inbox->resolve((string) $source['inbox']), 'inbox'];
        }

        $path = (string) ($source['path'] ?? '');
        $real = $path !== '' ? realpath($path) : false;

        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => basename($path)]);
        }

        return [$real, 'cli'];
    }
}
