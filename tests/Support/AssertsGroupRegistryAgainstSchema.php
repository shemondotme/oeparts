<?php

namespace Tests\Support;

use App\Services\Cleanup\SchemaAuditor;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Support\Facades\Schema;

/**
 * The guard that makes the group registry trustworthy: it is compared with the REAL migrated schema,
 * in both directions, on SQLite (the default suite) and on MySQL (phpunit.mysql.xml). Only a real
 * database sees raw DDL, conditional creates and table names built at run time; a regex scan of the
 * migration files cannot, which is why that scan (SchemaAuditor) is only the secondary check here.
 *
 * A new migration that adds a table fails these tests until the table is assigned to a group in
 * GroupRegistry (and RegistryTotals is raised in the same commit). That is the point.
 */
trait AssertsGroupRegistryAgainstSchema
{
    /** @return array<int, string> */
    protected function liveTables(): array
    {
        // On MySQL the listing spans every database on the server unless it is told which one.
        $connection = Schema::getConnection();
        $schema = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null;

        $tables = array_values(array_filter(
            Schema::getTableListing($schema, schemaQualified: false),
            static fn (string $t): bool => ! str_starts_with($t, 'sqlite_')
        ));
        sort($tables);

        return $tables;
    }

    protected function assertRegistryMatchesTheRealSchema(): void
    {
        $registry = new GroupRegistry;
        $live = $this->liveTables();

        $this->assertSame([], $registry->unclassified($live), 'tables that exist but no group owns: assign them in GroupRegistry');
        $this->assertSame([], $registry->phantoms($live), 'tables a group owns that do not exist');
        $this->assertCount(RegistryTotals::TOTAL_TABLES, $live, 'RegistryTotals::TOTAL_TABLES is out of date');
        $this->assertCount(RegistryTotals::SYSTEM_TABLES, $registry->systemTables(), 'RegistryTotals::SYSTEM_TABLES is out of date');
    }

    protected function assertSchemaAuditorAgreesWithTheRegistry(): void
    {
        $audit = (new SchemaAuditor)->audit();
        $registry = new GroupRegistry;

        $this->assertSame([], $audit['unexpected'], 'the migration scan and the real schema disagree (tables nobody created)');
        $this->assertSame([], $audit['expected_missing'], 'the migration scan expects tables the real schema lacks');

        $expected = (new SchemaAuditor)->expectedTables();
        sort($expected);
        $assigned = $registry->allTables();
        sort($assigned);

        $this->assertSame($expected, $assigned, 'the registry and the migration scan assign different tables');
    }

    protected function assertEveryExportableTableCanBePagedByKey(): void
    {
        $registry = new GroupRegistry;
        $withoutKey = [];

        foreach ($registry->exportableTables() as $table) {
            if (! $this->hasKeysetKey($table)) {
                $withoutKey[] = $table;
            }
        }

        $this->assertSame([], $withoutKey, 'tables with neither a primary key nor a NOT NULL unique index cannot be paged without OFFSET');
    }

    private function hasKeysetKey(string $table): bool
    {
        $columns = [];
        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = (bool) $column['nullable'];
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (! ($index['primary'] || $index['unique'])) {
                continue;
            }

            // SQLite reports an INTEGER PRIMARY KEY as primary with a NOT NULL rowid alias; MySQL marks the column.
            $nullable = array_filter($index['columns'], static fn (string $c): bool => ($columns[$c] ?? true) === true);

            if ($index['primary'] || $nullable === []) {
                return true;
            }
        }

        return false;
    }

    protected function assertSharedTableIsPartitionedExactly(): void
    {
        $registry = new GroupRegistry;
        $owners = $registry->ownersOf(GroupRegistry::SHARED_TABLE);

        $this->assertEqualsCanonicalizing([GroupKey::Catalog, GroupKey::Content], $owners);
        $this->assertEqualsCanonicalizing(['product', 'other'], [
            $registry->get(GroupKey::Catalog)->slices[GroupRegistry::SHARED_TABLE],
            $registry->get(GroupKey::Content)->slices[GroupRegistry::SHARED_TABLE],
        ]);

        // Every other table has exactly one owner.
        foreach ($registry->allTables() as $table) {
            if ($table !== GroupRegistry::SHARED_TABLE) {
                $this->assertCount(1, $registry->ownersOf($table), "{$table} must belong to exactly one group");
            }
        }
    }
}
