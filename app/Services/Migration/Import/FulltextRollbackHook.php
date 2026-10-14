<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Import\Contracts\RollbackHook;

/**
 * Part of the rollback, run AFTER the tables are back (building the index over the final rows is faster than over a half
 * loaded table): if the import took the products search index off, it goes back, whether the drop finished or not.
 */
final class FulltextRollbackHook implements RollbackHook
{
    public function __construct(private readonly FulltextIndex $index, private readonly ImportStateStore $store) {}

    public function name(): string
    {
        return 'index';
    }

    public function run(ImportState $state, ImportContext $context): bool
    {
        if (! in_array($this->index->name(), (array) ($state->package['indexes_dropped'] ?? []), true)) {
            return true;
        }

        if (! $this->index->exists()) {
            $state->expectSilenceFor((int) config('migration.import.long_stage_seconds', 7200));
            $this->store->save($state);

            $this->index->add();

            $state->clearExpectedSilence();
        }

        return true;
    }
}
