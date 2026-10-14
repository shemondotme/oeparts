<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\Import\RowLoader;
use App\Services\Migration\Import\SelectedGroups;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Stage 12. A group that was NOT imported ends in the state a fresh installer run leaves it in. The installer seeds the
 * content and shipping groups (`migration.seed_backed`); an import skips every seeder and empties every group, so for each
 * such group left out of the import this stage empties its tables once more and runs its seeders again. Emptying first makes
 * a resumed stage safe whatever a seeder does when it finds its rows already there.
 *
 * Runs after the audit, so the audit compares only what came from the package. The rows it seeded are listed in the report.
 */
final class DbSeedDefaultsStage implements ImportStage
{
    public function __construct(
        private readonly GroupRegistry $registry,
        private readonly RowLoader $loader,
        private readonly ImportMarker $marker,
        private readonly ImportStateStore $store,
    ) {}

    public function key(): string
    {
        return 'db_seed_defaults';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('seed.done')) {
            return ImportStepResult::done('The default data is in place.');
        }

        if (! $this->marker->isActive() || ! $state->flag('audit.done')) {
            throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_seed_defaults', 'why' => 'the data has not been checked']);
        }

        $this->loader->prepareSession();

        $groups = $this->groupsToSeed($state);
        $index = (int) ($state->stageState['gi'] ?? 0);
        $seeded = (array) ($state->package['seeded'] ?? []);

        while ($index < count($groups)) {
            [$group, $seeders] = $groups[$index];

            foreach ($this->registry->tablesOf([$group]) as $table) {
                $this->loader->truncate($table);
            }

            foreach ($seeders as $seeder) {
                $exit = Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\'.$seeder, '--force' => true]);

                if ($exit !== 0) {
                    throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_seed_defaults', 'why' => "{$seeder} failed"]);
                }
            }

            $seeded[$group->value] = $this->countsOf($group);
            $index++;
            $state->package['seeded'] = $seeded;
            $state->stageState['gi'] = $index;
            $state->touch();
            $this->store->save($state);

            if ($index < count($groups) && $context->expired()) {
                return ImportStepResult::progress($index / count($groups), 'Putting the default data back.');
            }
        }

        $state->package['seeded'] = $seeded;
        $state->setFlag('seed.done');
        $state->stageState = [];

        return ImportStepResult::done($seeded === [] ? 'No group needed its default data.' : 'The default data of '.implode(', ', array_keys($seeded)).' is in place.');
    }

    /** @return array<int, array{0: GroupKey, 1: array<int, string>}> the seed-backed groups this import did not load */
    private function groupsToSeed(ImportState $state): array
    {
        $todo = [];

        foreach ((array) config('migration.seed_backed', []) as $value => $seeders) {
            $group = GroupKey::tryFrom((string) $value);

            if ($group !== null && ! SelectedGroups::includes($state, $group)) {
                $todo[] = [$group, array_values((array) $seeders)];
            }
        }

        return $todo;
    }

    /** @return array<string, int> table => rows, for the tables that hold anything */
    private function countsOf(GroupKey $group): array
    {
        $counts = [];

        foreach ($this->registry->tablesOf([$group]) as $table) {
            $rows = (int) DB::table($table)->count();

            if ($rows > 0) {
                $counts[$table] = $rows;
            }
        }

        return $counts;
    }
}
