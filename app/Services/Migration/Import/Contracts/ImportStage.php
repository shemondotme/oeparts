<?php

namespace App\Services\Migration\Import\Contracts;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;

/**
 * One stage of an import. The manager calls step() again and again, each call a budgeted piece of work, until the
 * stage says it is done. A stage keeps its own small cursor in `$state->stageState` and its finished sub-steps in
 * `$state->flags`, so a killed process resumes exactly where it was; the manager saves the state after every step.
 *
 * A stage that throws a MigrationException tells the person why in plain words; any other throwable fails the run
 * (and, once data has been touched, rolls it back).
 */
interface ImportStage
{
    /** The stage's name in state files, logs and the kill matrix (`locate`, `verify`, `db_data`...). */
    public function key(): string;

    /** Does this stage change the target's data? Before the first one, a failure is a clean "failed", never a rollback. */
    public function destructive(): bool;

    public function step(ImportState $state, ImportContext $context): ImportStepResult;
}
