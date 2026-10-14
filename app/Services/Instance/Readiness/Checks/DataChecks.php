<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Migration\Export\FkEdge;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is the data that was moved in all there? Row counts against what the package said, references between tables, the files that
 * were put on the disk, the schema, the language the storefront needs, and that the shop is not left in maintenance.
 */
final class DataChecks
{
    private const GROUP = 'data';

    /** How many rows of a child table are looked at for each reference (a window, not the whole table). */
    private const FK_WINDOW = 5000;

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [
            new ReadinessProbe('rows_vs_manifest', self::GROUP, $this->rowsVsManifest(...)),
            new ReadinessProbe('fk_integrity_sample', self::GROUP, $this->fkIntegritySample(...)),
            new ReadinessProbe('file_counts_checksums', self::GROUP, $this->files(...)),
            new ReadinessProbe('migrations_current', self::GROUP, $this->migrationsCurrent(...)),
            new ReadinessProbe('default_language', self::GROUP, $this->defaultLanguage(...)),
            new ReadinessProbe('maintenance_off', self::GROUP, $this->maintenanceOff(...)),
        ];
    }

    /** Every table holds the rows the package said it carried: fewer is a failure, more only means the site has been used since. */
    public function rowsVsManifest(ReadinessContext $c): C
    {
        $label = 'Every table holds what the package carried';
        $loaded = (array) ($c->import()?->package['loaded'] ?? []);

        if ($loaded === []) {
            return $c->check('rows_vs_manifest', $label, self::GROUP, C::SKIP, 'This site was not made by an import, so there is nothing to compare with.');
        }

        $missing = [];
        $added = [];

        foreach ($loaded as $table => $expected) {
            $actual = Schema::hasTable((string) $table) ? (int) DB::table((string) $table)->count() : -1;

            if ($actual < (int) $expected) {
                $missing[(string) $table] = ['expected' => (int) $expected, 'found' => max(0, $actual)];
            } elseif ($actual > (int) $expected) {
                $added[] = (string) $table;
            }
        }

        if ($missing !== []) {
            return $c->check('rows_vs_manifest', $label, self::GROUP, C::FAIL, count($missing).' table(s) hold fewer rows than the package carried: '.implode(', ', array_keys($missing)).'.', ['tables' => $missing], 'Do not launch this site. Take the import back and run it again.');
        }

        if ($added !== []) {
            return $c->check('rows_vs_manifest', $label, self::GROUP, C::WARN, 'Rows were added since the import in: '.implode(', ', $added).'.', ['tables' => $added]);
        }

        return $c->check('rows_vs_manifest', $label, self::GROUP, C::PASS, count($loaded).' table(s) hold exactly what the package carried.');
    }

    /** Rows that point at a row that is not there, looked for in the first rows of every child table (the source may have had a few; many is a sign). */
    public function fkIntegritySample(ReadinessContext $c): C
    {
        $label = 'References between tables are intact';
        $tables = (new GroupRegistry)->exportableTables();
        $broken = [];

        foreach ((new FkGraph($tables))->edges() as $edge) {
            if ($edge->isSelfReference() || ! Schema::hasTable($edge->child) || ! Schema::hasTable($edge->parent)) {
                continue;
            }

            $orphans = $this->orphansIn($edge);

            if ($orphans > 0) {
                $broken[$edge->signature()] = $orphans;
            }
        }

        if ($broken === []) {
            return $c->check('fk_integrity_sample', $label, self::GROUP, C::PASS, 'No reference points at a missing row in the rows that were looked at.');
        }

        return $c->check('fk_integrity_sample', $label, self::GROUP, C::WARN, count($broken).' reference(s) point at rows that are not there.', ['references' => $broken], 'If the source site had them too this is harmless; otherwise take the import back.');
    }

    /** The files the import put on the disk are still there (a sample of what its journal says it created). */
    public function files(ReadinessContext $c): C
    {
        $label = 'The imported files are on the disk';
        $import = $c->import();
        $journal = $import === null ? null : $c->runsStore()->directory($import->id).DIRECTORY_SEPARATOR.'journal'.DIRECTORY_SEPARATOR.'files-created.ndjson';

        if ($journal === null || ! is_file($journal)) {
            return $c->check('file_counts_checksums', $label, self::GROUP, C::SKIP, 'This site did not receive files from an import.');
        }

        $disks = app()->make('migration.import_disks');
        $checked = 0;
        $missing = 0;
        $handle = fopen($journal, 'rb');

        while ($handle !== false && $checked < 500 && ($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);

            if (! is_array($row) || ! isset($disks[(string) ($row['d'] ?? '')])) {
                continue;
            }

            $checked++;

            if (! is_file($disks[$row['d']]['path'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) ($row['p'] ?? '')))) {
                $missing++;
            }
        }

        if ($handle !== false) {
            fclose($handle);
        }

        return $missing > 0
            ? $c->check('file_counts_checksums', $label, self::GROUP, C::FAIL, "{$missing} of {$checked} sampled imported file(s) are missing from the disk.", ['sampled' => $checked, 'missing' => $missing], 'Check that the storage folder is the one the import wrote to.')
            : $c->check('file_counts_checksums', $label, self::GROUP, C::PASS, "{$checked} sampled imported file(s) are on the disk.", ['sampled' => $checked]);
    }

    public function migrationsCurrent(ReadinessContext $c): C
    {
        $label = 'The database structure is up to date';
        $migrator = app(Migrator::class);
        $repository = $migrator->getRepository();
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];
        $files = array_keys($migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')])));
        $pending = array_values(array_diff($files, $ran));

        return $pending === []
            ? $c->check('migrations_current', $label, self::GROUP, C::PASS, 'No migration is pending.')
            : $c->check('migrations_current', $label, self::GROUP, C::FAIL, count($pending).' migration(s) have not run.', ['pending' => count($pending)], 'Run "php artisan migrate --force".');
    }

    /** The storefront needs a default, active language (the incident where the default language was deleted took every page down). */
    public function defaultLanguage(ReadinessContext $c): C
    {
        $label = 'The storefront has a default language';
        $ok = Schema::hasTable('languages') && DB::table('languages')->where('is_default', 1)->where('is_active', 1)->exists();

        return $ok
            ? $c->check('default_language', $label, self::GROUP, C::PASS, 'There is an active default language.')
            : $c->check('default_language', $label, self::GROUP, C::FAIL, 'No active language is marked as the default: every storefront page would answer "not found".', fix: 'Mark one active language as the default in the admin panel (Languages).');
    }

    public function maintenanceOff(ReadinessContext $c): C
    {
        $label = 'The shop is not in maintenance mode';
        $stored = DB::table('settings')->where('group', 'maintenance')->where('key', 'enabled')->value('value');

        return (string) $stored === '1'
            ? $c->check('maintenance_off', $label, self::GROUP, C::FAIL, 'Maintenance mode is switched on: visitors would see the maintenance page.', fix: 'Switch maintenance mode off in the admin panel.')
            : $c->check('maintenance_off', $label, self::GROUP, C::PASS, 'Maintenance mode is off.');
    }

    /** Rows among the first FK_WINDOW of the child (by id when it has one) whose reference points at a missing parent row. */
    private function orphansIn(FkEdge $edge): int
    {
        $window = DB::table($edge->child)->select($edge->childColumns);

        foreach ($edge->childColumns as $column) {
            $window->whereNotNull($column);
        }

        if (Schema::hasColumn($edge->child, 'id')) {
            $window->addSelect('id')->orderBy('id');
        }

        $window->limit(self::FK_WINDOW);

        $query = DB::query()->fromSub($window, 'w');

        return (int) $query->whereNotExists(function (Builder $parent) use ($edge): void {
            $parent->selectRaw('1')->from($edge->parent);

            foreach ($edge->childColumns as $position => $column) {
                $parent->whereColumn($edge->parent.'.'.$edge->parentColumns[$position], 'w.'.$column);
            }
        })->count();
    }
}
