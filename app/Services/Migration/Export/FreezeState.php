<?php

namespace App\Services\Migration\Export;

use App\Support\AtomicJsonFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The one record of a "freeze" export: the shop is showing the maintenance page so a consistent copy
 * can be taken. It lives in a single small file, storage/app/instance/export-freeze.json, outside the
 * database (the database is what is being copied) and outside every backup.
 *
 *   {"v":1,"run_id":12,"phase":"export"|"hold","engaged_at":...,"export_deadline_at":...,
 *    "hold_deadline_at":... or null,"previous_maintenance":{"present":true,"enabled":0},
 *    "paused_queues":["critical","default","low"],"released_at":null}
 *
 * Two deadlines, because a freeze has two lives. While the export runs the cap is `export_deadline_at`.
 * Once the package is sealed and verified the freeze may be HELD (so the old site stays frozen until DNS
 * moves) and the cap becomes `hold_deadline_at`; null there means "until the operator lifts it".
 * `previous_maintenance` remembers the operator's own maintenance setting, so lifting the freeze restores
 * it instead of blindly switching maintenance off.
 *
 * Everything that must decide "is this freeze still alive?" (the lock custody rule, the watchdog, the
 * request that lifts an expired freeze) reads it through this class, so there is one definition of alive.
 */
final class FreezeState
{
    public const PHASE_EXPORT = 'export';

    public const PHASE_HOLD = 'hold';

    /**
     * @param  array{present: bool, enabled: int|null}  $previousMaintenance
     * @param  array<int, string>  $pausedQueues
     */
    private function __construct(
        public readonly int $runId,
        public readonly string $phase,
        public readonly CarbonImmutable $engagedAt,
        public readonly CarbonImmutable $exportDeadlineAt,
        public readonly ?CarbonImmutable $holdDeadlineAt,
        public readonly array $previousMaintenance,
        public readonly array $pausedQueues,
        public readonly ?CarbonImmutable $releasedAt,
    ) {}

    public static function file(): AtomicJsonFile
    {
        return new AtomicJsonFile(storage_path('app/instance/export-freeze.json'));
    }

    /**
     * A new freeze in its export phase.
     *
     * @param  array{present: bool, enabled: int|null}  $previousMaintenance
     * @param  array<int, string>  $pausedQueues
     */
    public static function begin(int $runId, \DateTimeInterface $engagedAt, \DateTimeInterface $exportDeadlineAt, array $previousMaintenance, array $pausedQueues = []): self
    {
        return new self(
            $runId, self::PHASE_EXPORT, CarbonImmutable::instance($engagedAt), CarbonImmutable::instance($exportDeadlineAt),
            null, $previousMaintenance, array_values($pausedQueues), null
        );
    }

    /**
     * The freeze on disk, or null when there is none or it cannot be read. A file torn by a crash falls
     * back to its previous copy (AtomicJsonFile); one that is unreadable altogether is reported to the log
     * and treated as absent, because every caller needs an answer, not an exception.
     */
    public static function read(?AtomicJsonFile $file = null): ?self
    {
        $file ??= self::file();

        try {
            $data = $file->read();
        } catch (\RuntimeException $e) {
            Log::channel('migration')->warning('The export freeze file cannot be read and is ignored: '.$e->getMessage());

            return null;
        }

        if ($data === null) {
            return null;
        }

        try {
            return self::fromArray($data);
        } catch (\Throwable $e) {
            Log::channel('migration')->warning('The export freeze file is not valid and is ignored: '.$e->getMessage());

            return null;
        }
    }

    public function write(?AtomicJsonFile $file = null): void
    {
        ($file ?? self::file())->write($this->toArray());
    }

    /** Once the package is sealed: the cap becomes the hold deadline (null = until lifted by hand). */
    public function toHold(?\DateTimeInterface $holdDeadlineAt): self
    {
        return new self(
            $this->runId, self::PHASE_HOLD, $this->engagedAt, $this->exportDeadlineAt,
            $holdDeadlineAt === null ? null : CarbonImmutable::instance($holdDeadlineAt),
            $this->previousMaintenance, $this->pausedQueues, $this->releasedAt
        );
    }

    public function released(\DateTimeInterface $at): self
    {
        return new self(
            $this->runId, $this->phase, $this->engagedAt, $this->exportDeadlineAt, $this->holdDeadlineAt,
            $this->previousMaintenance, $this->pausedQueues, CarbonImmutable::instance($at)
        );
    }

    public function belongsToRun(int $runId): bool
    {
        return $this->runId === $runId;
    }

    /** When this freeze must end by itself; null only for a hold that was set to last until lifted by hand. */
    public function currentDeadline(): ?CarbonImmutable
    {
        return $this->phase === self::PHASE_HOLD ? $this->holdDeadlineAt : $this->exportDeadlineAt;
    }

    public function isExpired(?\DateTimeInterface $now = null): bool
    {
        $deadline = $this->currentDeadline();

        return $deadline !== null && $deadline->lessThanOrEqualTo(CarbonImmutable::instance($now ?? now()));
    }

    /** The shop is (still) frozen: not released and the current deadline has not passed. */
    public function isLive(?\DateTimeInterface $now = null): bool
    {
        return $this->releasedAt === null && ! $this->isExpired($now);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => 1,
            'run_id' => $this->runId,
            'phase' => $this->phase,
            'engaged_at' => $this->engagedAt->toIso8601String(),
            'export_deadline_at' => $this->exportDeadlineAt->toIso8601String(),
            'hold_deadline_at' => $this->holdDeadlineAt?->toIso8601String(),
            'previous_maintenance' => $this->previousMaintenance,
            'paused_queues' => $this->pausedQueues,
            'released_at' => $this->releasedAt?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        if (($data['v'] ?? null) !== 1) {
            throw new \InvalidArgumentException('Unknown freeze file version.');
        }

        if (! is_int($data['run_id'] ?? null) || ! in_array($data['phase'] ?? null, [self::PHASE_EXPORT, self::PHASE_HOLD], true)) {
            throw new \InvalidArgumentException('The freeze file has no valid run or phase.');
        }

        $previous = $data['previous_maintenance'] ?? null;
        if (! is_array($previous) || ! is_bool($previous['present'] ?? null)) {
            throw new \InvalidArgumentException('The freeze file does not remember the previous maintenance setting.');
        }

        $parse = static fn (mixed $value): ?CarbonImmutable => is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;

        $engaged = $parse($data['engaged_at'] ?? null);
        $exportDeadline = $parse($data['export_deadline_at'] ?? null);

        if ($engaged === null || $exportDeadline === null) {
            throw new \InvalidArgumentException('The freeze file has no start or deadline.');
        }

        return new self(
            $data['run_id'], $data['phase'], $engaged, $exportDeadline, $parse($data['hold_deadline_at'] ?? null),
            ['present' => $previous['present'], 'enabled' => isset($previous['enabled']) ? (int) $previous['enabled'] : null],
            array_values(array_map('strval', (array) ($data['paused_queues'] ?? []))), $parse($data['released_at'] ?? null)
        );
    }
}
