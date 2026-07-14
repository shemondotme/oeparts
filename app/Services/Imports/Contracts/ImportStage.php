<?php

namespace App\Services\Imports\Contracts;

use App\Models\ProductImportRun;
use App\Services\Imports\StageStepResult;

/**
 * A resumable unit of the Bulk Product Import engine.
 *
 * The engine drives a run through an ordered list of stages (validate header
 * → import rows). Each call to {@see step()} performs ONE chunk of work (one
 * batch of CSV rows) so the whole run can be advanced one AJAX poll at a time
 * and resumed after a crash/closed tab. The engine persists the returned
 * checkpoint state and feeds it back on the next call; the stage owns that
 * state's shape. Data a LATER stage needs (e.g. parsed headers) belongs on
 * $run->meta directly, not in this stage's own state — state is discarded
 * once a stage completes.
 */
interface ImportStage
{
    /** Stable identifier, used in progress messages. */
    public function key(): string;

    /**
     * Perform the next chunk of work for $run.
     *
     * @param  array  $state  opaque per-stage checkpoint; empty [] on first call
     */
    public function step(ProductImportRun $run, array $state): StageStepResult;
}
