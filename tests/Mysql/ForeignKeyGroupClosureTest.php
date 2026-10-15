<?php

namespace Tests\Mysql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the real server says about foreign keys. SQLite can under-report them (a key added by a later
 * ALTER TABLE is invisible to it), so the group-closure logic of the migration feature is proven
 * against information_schema here. This file starts as the spike that pins today's numbers; the
 * dependency-resolver tests extend it.
 *
 * If a number below changes because a feature added a table or a foreign key, that is the signal to
 * classify the new reference in GroupRegistry (which group owns the table, is the edge strong or weak)
 * and then update the number here in the same commit.
 */
class ForeignKeyGroupClosureTest extends MysqlTestCase
{
    /** @return array<string, int> parent table => number of distinct tables that reference it */
    private function referencingTables(): array
    {
        $rows = DB::select(
            'SELECT REFERENCED_TABLE_NAME AS parent, COUNT(DISTINCT TABLE_NAME) AS children FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL GROUP BY REFERENCED_TABLE_NAME'
        );

        return collect($rows)->mapWithKeys(fn ($row): array => [$row->parent => (int) $row->children])->all();
    }

    #[Test]
    public function the_four_big_parents_are_referenced_by_exactly_the_tables_the_registry_was_built_from(): void
    {
        $counts = $this->referencingTables();

        $this->assertSame(23, $counts['admins'], 'tables with a foreign key to admins');
        $this->assertSame(10, $counts['users'], 'tables with a foreign key to users');
        $this->assertSame(7, $counts['products'], 'tables with a foreign key to products');
        $this->assertSame(6, $counts['orders'], 'tables with a foreign key to orders');
    }

    #[Test]
    public function the_schema_has_the_table_and_foreign_key_totals_the_plan_counted(): void
    {
        $tables = (int) DB::selectOne("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->c;
        $foreignKeys = (int) DB::selectOne(
            'SELECT COUNT(DISTINCT CONSTRAINT_NAME) AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
        )->c;

        $this->assertSame(102, $tables, '101 application tables (Phase 9.5 added package_share_links) plus the framework migrations table');
        $this->assertSame(86, $foreignKeys);
    }

    #[Test]
    public function every_foreign_key_points_at_a_table_that_exists_and_no_key_is_composite_across_groups(): void
    {
        $dangling = DB::select(
            'SELECT k.TABLE_NAME AS child, k.REFERENCED_TABLE_NAME AS parent FROM information_schema.KEY_COLUMN_USAGE k '
            .'LEFT JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = k.TABLE_SCHEMA AND t.TABLE_NAME = k.REFERENCED_TABLE_NAME '
            .'WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL AND t.TABLE_NAME IS NULL'
        );

        $this->assertSame([], $dangling);

        // The dependency resolver treats an edge as one (child columns -> parent); a composite key
        // would need column-wise nullability handling. None exists today and this keeps it that way.
        $composite = DB::select(
            'SELECT TABLE_NAME AS child, CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL GROUP BY TABLE_NAME, CONSTRAINT_NAME HAVING COUNT(*) > 1'
        );

        $this->assertSame([], $composite, 'composite foreign keys need column-wise handling in FkGraph');
    }

    #[Test]
    public function the_mysql_leg_runs_on_the_database_product_the_ci_matrix_names(): void
    {
        $version = (string) DB::selectOne('SELECT VERSION() AS v')->v;

        $this->assertMatchesRegularExpression('/^(8\.|10\.|11\.)/', $version, "unexpected server {$version}");
    }
}
