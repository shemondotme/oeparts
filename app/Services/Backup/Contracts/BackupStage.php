<?php

namespace App\Services\Backup\Contracts;

use App\Models\BackupRun;
use App\Services\Backup\StageStepResult;

/**
 * A resumable unit of the Backup Engine (Module 14/21).
 *
 * The engine drives a run through an ordered list of stages (DB → files → env).
 * Each call to {@see step()} performs ONE chunk of work (one keyset page, one
 * file volume, …) so the whole run can be advanced one AJAX poll at a time and
 * resumed after a crash. The engine persists the returned checkpoint state and
 * feeds it back on the next call; the stage owns that state's shape.
 *
 * Chunk 2.1 defines this seam; the concrete DB (2.2), file (2.3) and env/
 * encryption (2.4) stages plug in here via config('backup.stages').
 */
interface BackupStage
{
    /** Stable identifier — one of BackupChunk::TYPE_* ('db' | 'files' | 'env'). */
    public function key(): string;

    /**
     * Perform the next chunk of work for $run.
     *
     * @param  array  $state  opaque per-stage checkpoint; empty [] on first call
     */
    public function step(BackupRun $run, array $state): StageStepResult;
}
