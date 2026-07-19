<?php

namespace App\Services\Backup;

use App\Models\BackupRun;

/**
 * Immutable snapshot of a backup run's progress, returned by each
 * {@see BackupManager::advance()} — the payload the AJAX-poll UI (Chunk 2.6)
 * renders and the CLI reports.
 */
class BackupProgress
{
    public function __construct(
        public readonly int $runId,
        public readonly string $status,   // running | success | failed
        public readonly bool $done,       // terminal state reached?
        public readonly int $percent = 0, // 0-100, best-effort estimate
        public readonly ?string $stage = null,
        public readonly int $partCount = 0,
        public readonly int $totalBytes = 0,
        public readonly ?string $message = null,
        public readonly ?string $error = null,
    ) {}

    public static function running(BackupRun $run, ?string $stage = null, ?string $message = null, int $percent = 0): self
    {
        return new self(
            runId: (int) $run->getKey(),
            status: BackupRun::STATUS_RUNNING,
            done: false,
            percent: max(0, min(99, $percent)), // never claim 100% before finalize() actually runs
            stage: $stage,
            partCount: (int) $run->part_count,
            totalBytes: (int) $run->total_bytes,
            message: $message,
        );
    }

    public static function success(BackupRun $run): self
    {
        return new self(
            runId: (int) $run->getKey(),
            status: BackupRun::STATUS_SUCCESS,
            done: true,
            percent: 100,
            partCount: (int) $run->part_count,
            totalBytes: (int) $run->total_bytes,
            message: 'Backup completed.',
        );
    }

    public static function failed(BackupRun $run, string $error): self
    {
        return new self(
            runId: (int) $run->getKey(),
            status: BackupRun::STATUS_FAILED,
            done: true,
            partCount: (int) $run->part_count,
            totalBytes: (int) $run->total_bytes,
            error: $error,
        );
    }

    public function toArray(): array
    {
        return [
            'run_id'      => $this->runId,
            'status'      => $this->status,
            'done'        => $this->done,
            'percent'     => $this->percent,
            'stage'       => $this->stage,
            'part_count'  => $this->partCount,
            'total_bytes' => $this->totalBytes,
            'message'     => $this->message,
            'error'       => $this->error,
        ];
    }
}
