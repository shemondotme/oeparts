<?php

namespace App\Services\Migration\Rewrite;

use App\Support\AtomicJsonFile;

/**
 * Where a domain rewrite has got to, small enough to save after every chunk: which step (the settings, then one
 * table at a time), the last primary key done in that step, and the counters. Resuming from it gives exactly the
 * result of an uninterrupted run, because every chunk is committed on its own and rewriting is idempotent.
 */
final class RewriteCursor
{
    /**
     * @param  array<string, array<string, int>>  $stats  step => counters
     */
    public function __construct(
        public string $planHash,
        public int $step = 0,
        public int $lastId = 0,
        public array $stats = [],
        public bool $done = false,
        public ?string $startedAt = null,
        public ?string $finishedAt = null,
    ) {}

    public static function fresh(RewritePlan $plan): self
    {
        return new self($plan->hash(), startedAt: now()->toIso8601String());
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['plan'] ?? ''), (int) ($data['step'] ?? 0), (int) ($data['last_id'] ?? 0),
            (array) ($data['stats'] ?? []), (bool) ($data['done'] ?? false), $data['started_at'] ?? null, $data['finished_at'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'v' => 1, 'plan' => $this->planHash, 'step' => $this->step, 'last_id' => $this->lastId,
            'stats' => $this->stats, 'done' => $this->done, 'started_at' => $this->startedAt, 'finished_at' => $this->finishedAt,
        ];
    }

    public function bump(string $step, string $counter, int $by = 1): void
    {
        $this->stats[$step][$counter] = ($this->stats[$step][$counter] ?? 0) + $by;
    }

    public function total(string $counter): int
    {
        return array_sum(array_map(static fn (array $s): int => (int) ($s[$counter] ?? 0), $this->stats));
    }

    /** The saved cursor of THIS plan, or a fresh one (a cursor made under another plan is never continued). */
    public static function load(AtomicJsonFile $file, RewritePlan $plan): self
    {
        try {
            $saved = $file->read();
        } catch (\RuntimeException) {
            $saved = null;
        }

        if ($saved === null || ($saved['plan'] ?? null) !== $plan->hash()) {
            return self::fresh($plan);
        }

        return self::fromArray($saved);
    }

    public function save(AtomicJsonFile $file): void
    {
        $file->write($this->toArray());
    }
}
