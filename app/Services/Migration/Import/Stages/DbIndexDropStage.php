<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\FulltextIndex;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Stage 8, conditional. Takes the products search index off before the bulk insert and `db_index_restore` builds it again
 * after, when `migration.import.fulltext_strategy` is `drop_readd` and the server has such an index (MySQL only; on MariaDB
 * and SQLite, and with the default `keep`, it does nothing and says so).
 *
 * The drop is announced in the state BEFORE the statement runs, so a process that dies between the two leaves a record that
 * says the index may be gone: the rollback then builds it again whether or not the drop finished, and a resumed stage asks
 * the server and treats an already dropped index as done.
 */
final class DbIndexDropStage implements ImportStage
{
    public function __construct(
        private readonly FulltextIndex $index,
        private readonly ImportMarker $marker,
        private readonly ImportStateStore $store,
    ) {}

    public function key(): string
    {
        return 'db_index_drop';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('index.drop.done')) {
            return ImportStepResult::done('The search index step is finished.');
        }

        if (! $this->marker->isActive() || ! $state->flag('clear.done')) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_index_drop', 'why' => 'the old data has not been cleared']);
        }

        $announced = in_array($this->index->name(), (array) ($state->package['indexes_dropped'] ?? []), true);

        if (! $announced && ! ($this->index->dropWanted() && $this->index->supported() && $this->index->exists())) {
            $state->setFlag('index.drop.done');

            return ImportStepResult::done('The search index stays in place.');
        }

        if (! $announced) {
            $state->package['indexes_dropped'] = [$this->index->name()];
            $this->store->save($state); // BEFORE the statement: a crash from here on is known to the rollback
        }

        if ($this->index->exists()) {
            $state->expectSilenceFor((int) config('migration.import.long_stage_seconds', 7200));
            $this->store->save($state);

            $this->index->drop();

            $state->clearExpectedSilence();
        }

        $state->setFlag('index.drop.done');

        return ImportStepResult::done('The search index was taken off for the load.');
    }
}
