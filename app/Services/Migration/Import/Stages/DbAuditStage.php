<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Export\FkEdge;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Import\Contracts\ImportStage;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Stage 11. Checks the loaded data against the package, still behind the closed door:
 *
 *   counts   every table holds exactly the rows the package's table of contents says. A difference is a failure (and so a
 *            rollback): rows went missing or appeared that should not have;
 *   orphans  for every foreign key between the loaded tables, how many rows point at a row that is not there. The load
 *            ran with constraints off, so this is where a broken reference shows. Orphans are a WARNING with counts, never
 *            a failure: the source site may have had them, and deleting them here would change data that was not ours to change.
 *
 * Time-boxed with a cursor over the foreign keys, so a big table's anti-join never has to finish inside one request.
 */
final class DbAuditStage implements ImportStage
{
    public function __construct(private readonly ImportStateStore $store) {}

    public function key(): string
    {
        return 'db_audit';
    }

    public function destructive(): bool
    {
        return true;
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag('audit.done')) {
            return ImportStepResult::done('The data was checked.');
        }

        $tables = array_keys($this->loadedTables($state));

        if (($state->stageState['phase'] ?? 'counts') === 'counts') {
            $this->checkCounts($state);
            $state->stageState = ['phase' => 'orphans', 'ei' => 0, 'orphans' => []];
        }

        $edges = $this->edges($tables);
        $index = (int) ($state->stageState['ei'] ?? 0);
        $orphans = (array) ($state->stageState['orphans'] ?? []);

        while ($index < count($edges)) {
            $edge = $edges[$index];
            $found = $this->orphansOf($edge);

            if ($found > 0) {
                $orphans[$edge->signature()] = $found;
            }

            $index++;
            $state->stageState = ['phase' => 'orphans', 'ei' => $index, 'orphans' => $orphans];
            $state->touch();
            $this->store->save($state);

            if ($index < count($edges) && $context->expired()) {
                return ImportStepResult::progress($index / max(1, count($edges)), 'Checking the references between tables.');
            }
        }

        $state->package['audit'] = ['orphans' => $orphans, 'checked_edges' => count($edges), 'tables' => count($tables)];
        $state->setFlag('audit.done');
        $state->stageState = [];

        return ImportStepResult::done($orphans === [] ? 'The data was checked: nothing is out of place.' : 'The data was checked: '.count($orphans).' reference(s) point at rows that are not there.');
    }

    /** @return array<string, int> table => rows the package says it holds, for the tables that were loaded */
    private function loadedTables(ImportState $state): array
    {
        return array_map('intval', (array) ($state->package['loaded'] ?? []));
    }

    private function checkCounts(ImportState $state): void
    {
        foreach ($this->loadedTables($state) as $table => $expected) {
            $actual = (int) DB::table($table)->count();

            if ($actual !== $expected) {
                throw new MigrationException(MigrationErrorCode::ImportFailedStep, ['stage' => 'db_audit', 'why' => "{$table} holds {$actual} rows, the package says {$expected}"]);
            }
        }
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, FkEdge>
     */
    private function edges(array $tables): array
    {
        $loaded = array_flip($tables);

        return array_values(array_filter(
            (new FkGraph($tables))->edges(),
            static fn (FkEdge $e): bool => ! $e->isSelfReference() && isset($loaded[$e->parent]),
        ));
    }

    /** How many rows of the child have a value in the key and no parent row with it. */
    private function orphansOf(FkEdge $edge): int
    {
        $query = DB::table($edge->child);

        foreach ($edge->childColumns as $column) {
            $query->whereNotNull($edge->child.'.'.$column);
        }

        return (int) $query->whereNotExists(function (Builder $parent) use ($edge): void {
            $parent->selectRaw('1')->from($edge->parent);

            foreach ($edge->childColumns as $position => $column) {
                $parent->whereColumn($edge->parent.'.'.$edge->parentColumns[$position], $edge->child.'.'.$column);
            }
        })->count();
    }
}
