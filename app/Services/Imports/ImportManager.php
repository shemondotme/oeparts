<?php

namespace App\Services\Imports;

use App\Models\ProductImportRun;
use App\Services\Imports\Contracts\ImportStage;
use App\Services\Imports\Exceptions\ImportException;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Bulk Product Import engine's chunked, resumable finite state machine.
 * Mirrors App\Services\Backup\BackupManager's shape exactly.
 *
 * Lifecycle:
 *   start()   → creates a `running` ProductImportRun, guards against a second
 *               concurrent import, initialises the checkpoint.
 *   advance() → performs ONE chunk of work (one stage step) and persists the
 *               checkpoint. Designed to be called once per wire:poll tick;
 *               also safe to resume after a crash/closed browser tab.
 *   finalize()→ (reached when all stages are done) writes the BulkUpdateLog
 *               audit row + invalidates caches, marks success.
 *   fail()    → marks failed; the partial import stays exactly as far as it
 *               got (each row was its own DB transaction, so no rollback of
 *               already-processed rows).
 *
 * Pure PHP, no queue worker required (rule #41). The concrete work lives in
 * pluggable stages (config('imports.stages')); this class only orchestrates.
 */
class ImportManager
{
    private const MAX_STEPS = 1_000_000;

    private const PROFILE = 'product_import';

    public function __construct(
        private readonly StageRegistry $stages,
        private readonly ProductImportService $importService,
    ) {}

    /** @throws ImportException if another import is already running */
    public function start(string $diskPath, string $disk, string $originalFilename, int $adminId, bool $updateExisting): ProductImportRun
    {
        if (ProductImportRun::where('status', ProductImportRun::STATUS_RUNNING)->exists()) {
            throw new ImportException('An import is already running. Wait for it to finish before starting another.');
        }

        $run = ProductImportRun::create([
            'admin_id'         => $adminId,
            'status'           => ProductImportRun::STATUS_RUNNING,
            'original_filename' => $originalFilename,
            'disk'             => $disk,
            'path'             => $diskPath,
            'update_existing'  => $updateExisting,
            'started_at'       => now(),
        ]);

        $run->setCheckpoint(['stage_index' => 0, 'stage_state' => []]);
        $run->save();

        return $run;
    }

    /** Advance the run by one chunk. Returns a progress snapshot for the poll UI. */
    public function advance(ProductImportRun $run): ImportProgress
    {
        if ($run->status === ProductImportRun::STATUS_SUCCESS) {
            return ImportProgress::success($run);
        }

        if ($run->status === ProductImportRun::STATUS_FAILED) {
            return ImportProgress::failed($run, (string) $run->error);
        }

        $stages     = $this->stages->forProfile(self::PROFILE);
        $checkpoint = $run->checkpoint();
        $index      = $checkpoint['stage_index'];

        if ($index >= count($stages)) {
            return $this->finalize($run);
        }

        /** @var ImportStage $stage */
        $stage = $stages[$index];

        try {
            $result = $stage->step($run, $checkpoint['stage_state']);
        } catch (\Throwable $e) {
            return $this->fail($run, '['.$stage->key().'] '.$e->getMessage());
        }

        if ($result->done) {
            $checkpoint['stage_index'] = $index + 1;
            $checkpoint['stage_state'] = [];
        } else {
            $checkpoint['stage_state'] = $result->state;
        }

        $run->setCheckpoint($checkpoint);
        $run->save();

        return ImportProgress::running($run, $stage->key(), $result->message);
    }

    /** Drive a run to a terminal state synchronously (tests / CLI). Prefer advance() behind a poll in the UI. */
    public function run(ProductImportRun $run): ProductImportRun
    {
        $steps = 0;

        while (! $run->isTerminal() && $steps++ < self::MAX_STEPS) {
            $this->advance($run);
            $run->refresh();
        }

        return $run;
    }

    public function fail(ProductImportRun $run, string $error): ImportProgress
    {
        $run->status      = ProductImportRun::STATUS_FAILED;
        $run->error       = Str::limit($error, 2000, '');
        $run->finished_at = now();
        $run->save();

        Log::error('Product import run '.$run->getKey().' failed: '.$error);

        return ImportProgress::failed($run, $error);
    }

    /** Finalise a run whose stages have all completed: write the audit log, invalidate caches, mark success. */
    private function finalize(ProductImportRun $run): ImportProgress
    {
        $this->importService->recordCompletion(
            $run->admin_id,
            $run->created_count,
            $run->updated_count,
            $run->skipped_count,
            array_map(fn (array $e) => "Row {$e['row']}: {$e['message']}", $run->errors ?? []),
            (string) $run->getMetaValue('file_hash', ''),
        );

        Storage::disk($run->disk)->delete($run->path);

        $run->status      = ProductImportRun::STATUS_SUCCESS;
        $run->finished_at = now();
        $run->save();

        return ImportProgress::success($run);
    }
}
