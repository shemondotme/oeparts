<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRun;

/**
 * Immutable snapshot of a cleanup run, returned by every CleanupManager::advance() — the
 * payload the polling UI renders and the CLI prints. Mirrors BackupProgress on purpose.
 *
 * The percentage is capped at 99 while the run is not terminal: 100% is only ever shown for
 * a run that has actually been reconciled and finished.
 */
final class CleanupProgress
{
    public function __construct(
        public readonly int $runId,
        public readonly string $status,
        public readonly bool $done,
        public readonly int $percent,
        public readonly ?string $step,
        public readonly ?string $message,
        public readonly ?string $target,
        public readonly int $targeted,
        public readonly int $deleted,
        public readonly int $remaining,
        public readonly ?int $bytesFreed,
        public readonly int $elapsedSeconds,
        public readonly ?int $etaSeconds,
        public readonly ?string $error,
        /** @var list<string> */
        public readonly array $reasons,
    ) {}

    public static function fromRun(CleanupRun $run): self
    {
        $terminal = $run->isTerminal();
        $elapsed = $run->started_at ? max(0, (int) $run->started_at->diffInSeconds($run->finished_at ?? now(), true)) : 0;
        $percent = $terminal && $run->status === CleanupRun::STATUS_SUCCESS
            ? 100
            : max(0, min($terminal ? 100 : 99, (int) $run->percent));

        return new self(
            runId: (int) $run->getKey(),
            status: (string) $run->status,
            done: $terminal,
            percent: $percent,
            step: $run->step,
            message: $run->message,
            target: $run->target,
            targeted: (int) $run->targeted,
            deleted: (int) $run->deleted,
            remaining: (int) $run->remaining,
            bytesFreed: $run->bytes_freed,
            elapsedSeconds: $elapsed,
            etaSeconds: $terminal ? null : self::eta($percent, $elapsed),
            error: $run->error,
            reasons: $run->reasons ?? [],
        );
    }

    /**
     * Linear ETA from elapsed time and percent. Hidden (null) until there is enough signal:
     * at least 3% done and 10 seconds in, otherwise the first guess is wildly wrong.
     */
    public static function eta(int $percent, int $elapsedSeconds): ?int
    {
        if ($percent < 3 || $percent >= 100 || $elapsedSeconds < 10) {
            return null;
        }

        return (int) round($elapsedSeconds * (100 - $percent) / $percent);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId,
            'status' => $this->status,
            'done' => $this->done,
            'percent' => $this->percent,
            'step' => $this->step,
            'message' => $this->message,
            'target' => $this->target,
            'targeted' => $this->targeted,
            'deleted' => $this->deleted,
            'remaining' => $this->remaining,
            'bytes_freed' => $this->bytesFreed,
            'elapsed_seconds' => $this->elapsedSeconds,
            'eta_seconds' => $this->etaSeconds,
            'error' => $this->error,
            'reasons' => $this->reasons,
        ];
    }
}
