<?php

namespace App\Services\Migration\Ux;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\ImportGroupChoice;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\Import\ImportManager;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\TargetClass;
use App\Services\Migration\Import\TargetFreshness;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * The admin door of an import, as plain calls the Migration page's wizard makes, one wizard step at a time:
 *
 *   open()     start a run for a package of the import folder with the passphrase (the run opens, verifies and checks it)
 *   settle()   give the run a few seconds to reach "waiting for you", or report why it stopped
 *   choose()   which groups to bring (the same resolver as the command line)
 *   check()    put the person's choices into the run and run the checks again
 *   confirm()  the typed REPLACE and the acknowledgements: after this the runner page takes over
 *
 * The run is the same object the command line and the runner page use, so a run begun here can be finished by
 * `oeparts:migrate:import --resume`, and the other way round. The runner token returned by open() is secret: only its hash
 * is kept on the server.
 */
final class AdminImportFlow
{
    public function __construct(
        private readonly ImportManager $manager,
        private readonly ImportStateStore $store,
        private readonly ImportInbox $inbox,
        private readonly TargetFreshness $freshness,
        private readonly GroupRegistry $registry,
    ) {}

    /** @return list<array{name: string, size: int, modified: int, stable: bool}> packages in the import folder, newest first */
    public function inboxPackages(): array
    {
        $stableFor = max(0, (int) config('migration.installer.stable_seconds', 30));

        return array_map(
            static fn (array $p): array => $p + ['stable' => time() - $p['modified'] >= $stableFor],
            $this->inbox->list(),
        );
    }

    /**
     * @param  array<string, mixed>  $initiator  admin id, email, address
     * @return array{run_id: string, token: string}
     *
     * @throws MigrationException the package is not found or not a package, the passphrase is empty or wrong or locked
     */
    public function open(string $packageName, #[\SensitiveParameter] string $passphrase, array $initiator, string $clientKey): array
    {
        $started = $this->manager->start(['inbox' => $packageName], $passphrase, $initiator, 'import', $clientKey, ['door' => 'admin']);
        $state = $started['state'];
        $progress = $started['progress'];

        if (isset($progress['input_error'])) {
            $this->manager->discard($state->id); // nothing was changed; do not leave a run that can never continue

            throw new MigrationException(MigrationErrorCode::from((string) $progress['input_error']), (array) ($progress['input_params'] ?? []));
        }

        return ['run_id' => $state->id, 'token' => $started['token']];
    }

    /**
     * Let the run work for a few seconds.
     *
     * @return array{ready: bool, failed: bool, status: string, percent: float, detail: string, error: array<string, mixed>|null}
     */
    public function settle(string $runId, float $seconds = 15.0): array
    {
        $deadline = microtime(true) + max(0.0, $seconds);

        do {
            $progress = $this->manager->advance($runId, min(5.0, max(0.5, $deadline - microtime(true))));
            $state = $this->store->find($runId);

            if ($state === null || $state->status !== ImportStatus::Running) {
                break;
            }
        } while (microtime(true) < $deadline);

        $state ??= $this->store->find($runId);
        $status = $state?->status;

        return [
            'ready' => $state !== null && $status === ImportStatus::AwaitingConfirmation && $state->flag('preflight.done'),
            'failed' => $state === null || $status === ImportStatus::Failed || $status === ImportStatus::RolledBack,
            'status' => (string) ($status->value ?? 'missing'),
            'percent' => (float) ($progress['percent'] ?? 0.0),
            'detail' => (string) ($progress['detail'] ?? ''),
            'error' => $state?->error,
        ];
    }

    /** @return array{percent: float, detail: string} how far the run's checking has come, for a progress line */
    public function progress(string $runId): array
    {
        $status = $this->manager->status($runId);

        return ['percent' => (float) ($status['percent'] ?? 0.0), 'detail' => (string) ($status['detail'] ?? '')];
    }

    /** The file name (in the import folder) the run was made for, or null when there is no such run. */
    public function packageName(string $runId): ?string
    {
        $state = $this->store->find($runId);

        return $state === null ? null : basename((string) ($state->package['path'] ?? ''));
    }

    /**
     * What the package is, for the person who just opened it.
     *
     * @return array{groups: list<string>, fingerprint: string, source_host: string, has_secrets: bool, rows: int}
     */
    public function summary(string $runId): array
    {
        $state = $this->state($runId);
        $package = $state->package;

        return [
            'groups' => array_values(array_map('strval', (array) ($package['groups'] ?? []))),
            'fingerprint' => (string) ($package['fingerprint'] ?? ''),
            'source_host' => (string) ($package['source']['domain'] ?? ''),
            'has_secrets' => (bool) ($package['has_secrets'] ?? $package['source']['secrets_included'] ?? false),
            'rows' => (int) array_sum(array_map('intval', (array) ($package['tables'] ?? []))),
        ];
    }

    /**
     * @param  array<int, string>  $groups  used when no preset is chosen; none at all means "just the site basics"
     * @return array{groups: list<string>, added: list<string>}
     *
     * @throws \InvalidArgumentException a group the package does not hold, or a missing dependency
     */
    public function choose(string $runId, ?string $preset, array $groups): array
    {
        $held = (array) ($this->state($runId)->package['groups'] ?? []);
        $groups = array_values(array_unique(array_map('strval', $groups)));
        $groups = $groups === [] ? [GroupKey::Core->value] : $groups; // an empty list would mean "everything"

        $result = (new ImportGroupChoice($this->registry))->resolve(array_values(array_map('strval', $held)), $preset ?: null, $preset ? null : implode(',', $groups), null);

        return ['groups' => array_values($result['groups']), 'added' => array_values($result['added'])];
    }

    /**
     * Put the choices into the run and run the checks again (they depend on them).
     *
     * @param  array<string, mixed>  $options  launch, url, include_secrets, mail_allowlist
     * @return array{ready: bool, failed: bool, status: string, percent: float, detail: string, error: array<string, mixed>|null}
     */
    public function check(string $runId, array $options, float $seconds = 15.0): array
    {
        $this->store->update($runId, static function (ImportState $state) use ($options): void {
            foreach ($options as $key => $value) {
                if ($value === null) {
                    unset($state->options[$key]); // a choice that was taken back (the allow-list of a test copy that is now live)
                } else {
                    $state->options[$key] = $value;
                }
            }
        });

        $this->manager->rerunPreflight($runId);

        return $this->settle($runId, $seconds);
    }

    /**
     * What the checks said.
     *
     * @return array{checks: list<array{key: string, status: string, label: string, message: string, ack: string|null}>, can_proceed: bool, required_acks: list<string>, populated: bool, business_rows: array<string, int>}
     */
    public function preflight(string $runId): array
    {
        $preflight = (array) ($this->state($runId)->package['preflight'] ?? []);
        $checks = [];
        $populated = false;
        $business = [];

        foreach ((array) ($preflight['checks'] ?? []) as $check) {
            $checks[] = [
                'key' => (string) ($check['key'] ?? ''),
                'status' => (string) ($check['status'] ?? ''),
                'label' => (string) ($check['label'] ?? $check['key'] ?? ''),
                'message' => (string) ($check['message'] ?? ''),
                'ack' => isset($check['ack']) && is_string($check['ack']) && $check['ack'] !== '' ? $check['ack'] : null,
            ];

            if (($check['key'] ?? '') === 'target_state') {
                $populated = ($check['meta']['class'] ?? '') === TargetClass::Populated->value;
                $business = array_map('intval', (array) ($check['meta']['business_rows'] ?? []));
            }
        }

        return [
            'checks' => $checks,
            'can_proceed' => (bool) ($preflight['can_proceed'] ?? false),
            'required_acks' => array_values(array_map('strval', (array) ($preflight['required_acks'] ?? []))),
            'populated' => $populated,
            'business_rows' => $business,
        ];
    }

    /**
     * How many rows each part of THIS site holds, i.e. what the import empties: so "this replaces everything on this site" is
     * literally true on the confirm step. System tables (logs of the engine itself) are not counted, as in the checks.
     *
     * @return array<string, int> group key => rows, only parts that hold rows, biggest first
     */
    public function rowsPerPart(): array
    {
        $parts = [];

        foreach ($this->freshness->assess()->tableRows as $table => $rows) {
            $owner = $this->registry->ownerOf($table)->value ?? GroupKey::System->value;
            $parts[$owner] = ($parts[$owner] ?? 0) + (int) $rows;
        }

        arsort($parts);

        return $parts;
    }

    /**
     * The deliberate act. The checks are re-run by the manager against what changed since; after this the run is "confirmed" and
     * the runner page (with the token) drives it.
     *
     * @param  array<int, string>  $acks
     * @param  array<int, string>  $groups
     *
     * @throws MigrationException not ready, a failed check, a missing acknowledgement, REPLACE not typed, a package or site that changed
     */
    public function confirm(string $runId, array $acks, string $typed, array $groups): void
    {
        $this->manager->confirm($runId, $acks, ['door' => 'admin', 'typed' => $typed, 'options' => ['groups' => $groups]]);
    }

    /** Throw away a run that has not been confirmed (nothing on the site was touched by it). */
    public function discard(string $runId): void
    {
        $state = $this->store->find($runId);

        if ($state !== null && ! $state->flag('confirmed') && ! $state->isDestructive()) {
            $this->manager->discard($runId);
        }
    }

    /**
     * A run that is not the caller's own and is still alive, so the page can say so. `discardable` is true for a half-prepared run
     * of the admin panel that nothing has confirmed: its secure token only ever existed in the browser tab that made it, so it can
     * never be continued from here.
     *
     * @return array{id: string, status: string, door: string, started: string, discardable: bool}|null
     */
    public function otherRun(?string $ownRunId): ?array
    {
        $state = $this->store->active();

        if ($state === null || $state->id === $ownRunId) {
            return null;
        }

        $door = (string) ($state->options['door'] ?? 'cli');

        return [
            'id' => $state->id,
            'status' => $state->status->value,
            'door' => $door,
            'started' => $state->createdAt,
            'discardable' => $door === 'admin' && $state->kind === 'import' && ! $state->flag('confirmed') && ! $state->isDestructive() && $state->status !== ImportStatus::RollingBack,
        ];
    }

    private function state(string $runId): ImportState
    {
        return $this->store->find($runId) ?? throw new MigrationException(MigrationErrorCode::NotReadyToConfirm);
    }
}
