<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;

/**
 * Stage 7. The first stage that changes DATA: every table of the site outside the system group is emptied, the tables
 * of the groups being imported AND of the groups not being imported (those stay empty until their defaults are put
 * back, so nothing of the old site mixes with the new one). The system tables and the migration ledger are never touched.
 *
 * The moment it is entered the run is marked destructive and that mark is SAVED before the first statement runs: from
 * here on a failure means "roll back", never "nothing happened". It refuses to run unless the site is closed (the
 * marker exists and the arm stage finished). Re-running is safe: emptying an empty table changes nothing.
 */
final class DbClearStage implements ImportStage
{
    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly RowLoader $loader,
        private readonly ImportMarker $marker,
        private readonly ImportStateStore $store,
    ) {}

    public function key(): string
    {
        return 'db_clear';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('clear.done')) {
            return ImportStepResult::done('The old data is cleared.');
        }

        if (! $this->marker->isActive() || ! $state->flag('arm.repause')) {
            // Never empty a table of a site that is still open to the world.
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_clear', 'why' => 'the site is not closed']);
        }

        if (! $state->isDestructive()) {
            $state->markDestructive();
            $this->store->save($state); // saved BEFORE the first statement: a crash from here on is a rollback, not "nothing happened"
        }

        $this->loader->prepareSession();

        $tables = $this->tables();
        $index = (int) ($state->stageState['ti'] ?? 0);

        while ($index < count($tables)) {
            $this->loader->truncate($tables[$index]);
            $index++;
            $state->stageState['ti'] = $index;

            if ($index < count($tables) && $context->expired()) {
                $this->store->save($state);

                return ImportStepResult::progress($index / max(1, count($tables)), 'Clearing the old data.');
            }
        }

        $state->setFlag('clear.done');
        $state->stageState = [];

        return ImportStepResult::done('The old data is cleared.');
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        $tables = $this->registry->exportableTables();
        sort($tables);

        return $tables;
    }
}
