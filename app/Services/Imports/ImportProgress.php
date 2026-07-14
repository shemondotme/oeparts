<?php

namespace App\Services\Imports;

use App\Models\ProductImportRun;

/**
 * Immutable snapshot of an import run's progress, returned by each
 * {@see ImportManager::advance()} — the payload the wire:poll UI renders.
 */
class ImportProgress
{
    public function __construct(
        public readonly int $runId,
        public readonly string $status,   // running | success | failed
        public readonly bool $done,       // terminal state reached?
        public readonly ?string $stage = null,
        public readonly ?int $totalRows = null,
        public readonly int $processedRows = 0,
        public readonly int $createdCount = 0,
        public readonly int $updatedCount = 0,
        public readonly int $skippedCount = 0,
        public readonly int $errorCount = 0,
        public readonly ?string $message = null,
        public readonly ?string $error = null,
    ) {}

    public static function running(ProductImportRun $run, ?string $stage = null, ?string $message = null): self
    {
        return new self(...self::snapshot($run), status: ProductImportRun::STATUS_RUNNING, done: false, stage: $stage, message: $message);
    }

    public static function success(ProductImportRun $run): self
    {
        return new self(...self::snapshot($run), status: ProductImportRun::STATUS_SUCCESS, done: true, message: 'Import completed.');
    }

    public static function failed(ProductImportRun $run, string $error): self
    {
        return new self(...self::snapshot($run), status: ProductImportRun::STATUS_FAILED, done: true, error: $error);
    }

    /** @return array{runId:int,totalRows:?int,processedRows:int,createdCount:int,updatedCount:int,skippedCount:int,errorCount:int} */
    private static function snapshot(ProductImportRun $run): array
    {
        return [
            'runId'         => (int) $run->getKey(),
            'totalRows'     => $run->total_rows,
            'processedRows' => (int) $run->processed_rows,
            'createdCount'  => (int) $run->created_count,
            'updatedCount'  => (int) $run->updated_count,
            'skippedCount'  => (int) $run->skipped_count,
            'errorCount'    => (int) $run->error_count,
        ];
    }

    public function toArray(): array
    {
        return [
            'run_id'         => $this->runId,
            'status'         => $this->status,
            'done'           => $this->done,
            'stage'          => $this->stage,
            'total_rows'     => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'created_count'  => $this->createdCount,
            'updated_count'  => $this->updatedCount,
            'skipped_count'  => $this->skippedCount,
            'error_count'    => $this->errorCount,
            'message'        => $this->message,
            'error'          => $this->error,
        ];
    }
}
