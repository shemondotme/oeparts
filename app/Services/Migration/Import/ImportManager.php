<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
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
    ) {}

    /**
     * Begin a run for a package and take its first step (finding the package and opening it with the passphrase).
     *
     * @param  array{path?: string, inbox?: string}  $source  an absolute readable path (the command line) or a file name in the inbox (the web)
     * @param  array<string, mixed>  $initiator  who is doing this: admin id, email, address
     * @return array{state: ImportState, token: string, progress: array<string, mixed>} the runner token is returned ONCE, only its hash is kept
     *
     * @throws MigrationException the package cannot be found, or is not a package
     */
    public function start(array $source, ?string $passphrase, array $initiator = [], string $kind = 'import', string $clientKey = 'cli'): array
    {
        [$path, $origin] = $this->resolveSource($source);

        $token = bin2hex(random_bytes(32));
        $state = $this->store->create($kind, [
            'package' => ['path' => $path, 'origin' => $origin],
            'initiator' => $initiator,
            'token_hash' => hash('sha256', $token),
            'stage' => $this->stages->keys($kind)[0] ?? 'locate',
        ]);

        $progress = $this->advance($state->id, 0.0, $passphrase, $clientKey);

        return ['state' => $this->store->find($state->id) ?? $state, 'token' => $token, 'progress' => $progress];
    }

    public function advance(string $runId, float $budgetSeconds, ?string $passphrase = null, string $clientKey = 'cli'): array
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
    public function provideSecret(string $runId, string $passphrase, string $clientKey = 'cli'): array
    {
        return $this->advance($runId, 0.0, $passphrase, $clientKey);
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
        exec('rm -rf '.escapeshellarg($this->store->directory($runId)));
    }

    // ---------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function advanceLocked(string $runId, float $budgetSeconds, ?string $passphrase, string $clientKey): array
    {
        $state = $this->store->find($runId);

        if ($state === null || $state->status->isTerminal() || $state->status === ImportStatus::AwaitingConfirmation) {
            return $this->status($runId) + ['advanced' => false];
        }

        $deadline = microtime(true) + max(0.0, $budgetSeconds);
        $context = new ImportContext($deadline, $this->escrow, $passphrase, $clientKey);
        $passphrase = null; // from here on only the context holds it, for this call

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

            try {
                $result = $stage->step($state, $context);
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

    /** The stage is finished: next stage, or the end of the road for this kind of run. */
    private function moveOn(ImportState $state): void
    {
        $next = $this->stages->nextAfter($state->stage, $state->kind);

        if ($next !== null) {
            $state->stage = $next;
            $state->stageState = [];

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

        if ($state->isDestructive()) {
            $state->status = ImportStatus::RollingBack;
        } else {
            $state->status = ImportStatus::Failed;
            $state->finishedAt = now()->toIso8601String();
            $this->escrow->delete(ImportContext::escrowId($state->id));
        }

        $this->store->save($state);
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
