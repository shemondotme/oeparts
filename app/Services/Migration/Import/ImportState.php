<?php

namespace App\Services\Migration\Import;

use Carbon\Carbon;

/**
 * Everything an import run needs to remember, in one small document (storage/app/migration/runs/<id>/state.json).
 * It lives in a file, never in a table, because the import replaces the tables.
 *
 * It holds no secret: the runner token is stored as its hash, the package key is in the escrow (never here), and
 * the admin's password hash for a break-glass account is scrubbed before the run ends. What it does hold is enough
 * to resume from any point: the stage, that stage's own small cursor, the flags of the sub-steps already done, and
 * the identity of the package being imported, so that touching the file between verify and confirm is noticed.
 */
final class ImportState
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $stageState  the running stage's own cursor
     * @param  array<string, bool>  $flags  sub-steps already done ("arm.marker", "complete.escrow", ...)
     * @param  array<string, mixed>  $package  the pinned identity of the package (PackageIdentity::capture())
     * @param  array<string, mixed>  $initiator  who started it: admin id, email, address
     * @param  array<string, mixed>  $options  frozen when the run was confirmed
     * @param  array<int, string>  $indexesDropped  indexes dropped for speed that must come back, even after a crash
     * @param  array<string, mixed>|null  $error  code and parameters of what stopped the run
     */
    public function __construct(
        public readonly string $id,
        public string $kind = 'import',
        public ImportStatus $status = ImportStatus::Running,
        public string $stage = 'locate',
        public array $stageState = [],
        public array $flags = [],
        public array $package = [],
        public array $initiator = [],
        public array $options = [],
        public ?string $tokenHash = null,
        public ?string $escrowId = null,
        public string $createdAt = '',
        public string $heartbeatAt = '',
        public ?string $destructiveStartedAt = null,
        public ?string $longRunningUntil = null,
        public int $consecutiveFailures = 0,
        public ?string $nextSelfDriveAt = null,
        public array $indexesDropped = [],
        public ?array $error = null,
        public ?string $finishedAt = null,
    ) {
        $now = now()->toIso8601String();
        $this->createdAt = $createdAt !== '' ? $createdAt : $now;
        $this->heartbeatAt = $heartbeatAt !== '' ? $heartbeatAt : $now;
    }

    /** Record that the run is alive. Every advance does this; the janitors and the watchdog read it. */
    public function touch(): void
    {
        $this->heartbeatAt = now()->toIso8601String();
    }

    public function flag(string $name): bool
    {
        return ($this->flags[$name] ?? false) === true;
    }

    public function setFlag(string $name): void
    {
        $this->flags[$name] = true;
    }

    /** Has the first statement that changes DATA been reached? (Before it, a failure is "failed", never "rolled back".) */
    public function isDestructive(): bool
    {
        return $this->destructiveStartedAt !== null;
    }

    public function markDestructive(): void
    {
        $this->destructiveStartedAt ??= now()->toIso8601String();
    }

    /**
     * An unchunkable statement (one ALTER that takes minutes) says so BEFORE it starts, so that nobody mistakes
     * a long silence for a dead run: until this moment the run counts as alive whatever its heartbeat says.
     */
    public function expectSilenceFor(int $seconds): void
    {
        $this->longRunningUntil = now()->addSeconds(max(0, $seconds))->toIso8601String();
    }

    public function clearExpectedSilence(): void
    {
        $this->longRunningUntil = null;
    }

    /** Is this run alive by the janitor's rule: a fresh heartbeat, or an announced long statement still within its time? */
    public function isAlive(int $staleAfterSeconds): bool
    {
        if ($this->status->isTerminal()) {
            return false;
        }

        if ($this->longRunningUntil !== null && now()->lt(Carbon::parse($this->longRunningUntil))) {
            return true;
        }

        return Carbon::parse($this->heartbeatAt)->gte(now()->subSeconds($staleAfterSeconds));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'id' => $this->id, 'kind' => $this->kind, 'status' => $this->status->value, 'stage' => $this->stage,
            'stage_state' => $this->stageState, 'flags' => $this->flags, 'package' => $this->package,
            'initiator' => $this->initiator, 'options' => $this->options, 'token_hash' => $this->tokenHash,
            'escrow_id' => $this->escrowId, 'created_at' => $this->createdAt, 'heartbeat_at' => $this->heartbeatAt,
            'destructive_started_at' => $this->destructiveStartedAt, 'long_running_until' => $this->longRunningUntil,
            'consecutive_failures' => $this->consecutiveFailures, 'next_self_drive_at' => $this->nextSelfDriveAt,
            'indexes_dropped' => $this->indexesDropped, 'error' => $this->error, 'finished_at' => $this->finishedAt,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            kind: (string) ($data['kind'] ?? 'import'),
            status: ImportStatus::tryFrom((string) ($data['status'] ?? '')) ?? ImportStatus::Running,
            stage: (string) ($data['stage'] ?? 'locate'),
            stageState: (array) ($data['stage_state'] ?? []),
            flags: array_map(static fn ($v): bool => $v === true, (array) ($data['flags'] ?? [])),
            package: (array) ($data['package'] ?? []),
            initiator: (array) ($data['initiator'] ?? []),
            options: (array) ($data['options'] ?? []),
            tokenHash: $data['token_hash'] ?? null,
            escrowId: $data['escrow_id'] ?? null,
            createdAt: (string) ($data['created_at'] ?? ''),
            heartbeatAt: (string) ($data['heartbeat_at'] ?? ''),
            destructiveStartedAt: $data['destructive_started_at'] ?? null,
            longRunningUntil: $data['long_running_until'] ?? null,
            consecutiveFailures: (int) ($data['consecutive_failures'] ?? 0),
            nextSelfDriveAt: $data['next_self_drive_at'] ?? null,
            indexesDropped: array_values(array_map('strval', (array) ($data['indexes_dropped'] ?? []))),
            error: isset($data['error']) && is_array($data['error']) ? $data['error'] : null,
            finishedAt: $data['finished_at'] ?? null,
        );
    }
}
