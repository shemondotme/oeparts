<?php

namespace App\Services\Migration;

/**
 * One progress shape for import, fetch, upload and dry run, so the installer screen, the admin
 * page and the CLI render the same thing. (Export adapts BackupProgress through a presenter.)
 */
final class MigrationProgress
{
    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    private const TERMINAL = [self::SUCCESS, self::FAILED, self::CANCELLED];

    /**
     * @param  array{rows: int, rowsTotal: int, files: int, filesTotal: int, bytes: int}  $counters
     * @param  array{code: string, params: array<string, scalar|null>}|null  $error
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $kind,
        public readonly string $status,
        public readonly float $rawPercent = 0.0,
        public readonly string $phase = '',
        public readonly int $phaseIndex = 0,
        public readonly int $phaseCount = 0,
        public readonly string $message = '',
        public readonly array $counters = ['rows' => 0, 'rowsTotal' => 0, 'files' => 0, 'filesTotal' => 0, 'bytes' => 0],
        public readonly int $elapsedSeconds = 0,
        public readonly int $workedMs = 0,
        public readonly bool $working = false,
        public readonly ?array $error = null,
        public readonly array $warnings = [],
        public readonly bool $resumable = false,
        public readonly bool $canRollback = false,
        public readonly bool $canRestart = false,
    ) {}

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /** Never shows 100% until the run really is finished: a bar stuck at 99 is honest, one that jumps back is not. */
    public function percent(): int
    {
        $percent = (int) floor(max(0.0, min(100.0, $this->rawPercent)));

        if ($this->status === self::SUCCESS) {
            return 100;
        }

        return min($percent, 99);
    }

    /**
     * Linear estimate of the seconds left. Hidden (null) until there is enough signal: at least 3%
     * done and 10 seconds in, otherwise the first guess is wildly wrong. Same rule as the cleanup
     * engine's progress.
     */
    public function etaSeconds(): ?int
    {
        $percent = $this->percent();

        if ($this->isTerminal() || $percent < 3 || $this->elapsedSeconds < 10) {
            return null;
        }

        return (int) round($this->elapsedSeconds * (100 - $percent) / $percent);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'runId' => $this->runId,
            'kind' => $this->kind,
            'status' => $this->status,
            'done' => $this->isTerminal(),
            'percent' => $this->percent(),
            'phase' => $this->phase,
            'phaseIndex' => $this->phaseIndex,
            'phaseCount' => $this->phaseCount,
            'message' => $this->message,
            'counters' => $this->counters,
            'elapsedSeconds' => $this->elapsedSeconds,
            'etaSeconds' => $this->etaSeconds(),
            'workedMs' => $this->workedMs,
            'working' => $this->working,
            'error' => $this->error,
            'warnings' => $this->warnings,
            'resumable' => $this->resumable,
            'canRollback' => $this->canRollback,
            'canRestart' => $this->canRestart,
        ];
    }
}
