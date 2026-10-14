<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\FulltextIndex;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;

/**
 * Stage 10, conditional. Builds the products search index again if `db_index_drop` took it off. One long statement that
 * cannot be cut into steps: the state says so before it starts (so a long silence is not taken for a dead run), and if
 * the process dies while MySQL is still building, the next call asks the server and treats an index that exists as done.
 */
final class DbIndexRestoreStage implements ImportStage
{
    public function __construct(
        private readonly FulltextIndex $index,
        private readonly ImportStateStore $store,
    ) {}

    public function key(): string
    {
        return 'db_index_restore';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('index.restore.done')) {
            return ImportStepResult::done('The search index step is finished.');
        }

        if (! in_array($this->index->name(), (array) ($state->package['indexes_dropped'] ?? []), true)) {
            $state->setFlag('index.restore.done');

            return ImportStepResult::done('The search index was never taken off.');
        }

        if (! $this->index->exists()) {
            $state->expectSilenceFor((int) config('migration.import.long_stage_seconds', 7200));
            $this->store->save($state);

            $this->index->add();

            $state->clearExpectedSilence();
        }

        $state->setFlag('index.restore.done');

        return ImportStepResult::done('The search index was built again.');
    }
}
