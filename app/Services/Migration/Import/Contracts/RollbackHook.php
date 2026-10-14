<?php

namespace App\Services\Migration\Import\Contracts;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;

/**
 * One piece of an import's undo that belongs to a later part of the engine (replaying the files journal, putting a
 * dropped search index back). The rollback runs every registered hook, in order, before it reloads the database.
 */
interface RollbackHook
{
    /** Its name in the state's `rollback.<name>` flag. */
    public function name(): string;

    /** Do a bounded piece of the work. True when it has nothing more to do (and so on every later call). */
    public function run(ImportState $state, ImportContext $context): bool;
}
