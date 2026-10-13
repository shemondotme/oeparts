<?php

namespace Tests\Mysql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsGroupRegistryAgainstSchema;

/**
 * The same registry guards as tests/Unit/Migration/GroupRegistryTest, against a real MySQL / MariaDB
 * schema: information_schema sees every table and key the migrations really produced, including ones
 * added by raw DDL or later ALTERs that SQLite does not report.
 */
class GroupRegistrySchemaTest extends MysqlTestCase
{
    use AssertsGroupRegistryAgainstSchema;

    #[Test]
    public function the_registry_and_the_real_mysql_schema_list_exactly_the_same_tables(): void
    {
        $this->assertRegistryMatchesTheRealSchema();
    }

    #[Test]
    public function the_migration_scan_agrees_with_the_registry_on_mysql_too(): void
    {
        $this->assertSchemaAuditorAgreesWithTheRegistry();
    }

    #[Test]
    public function every_table_belongs_to_exactly_one_group_except_the_shared_seo_table(): void
    {
        $this->assertSharedTableIsPartitionedExactly();
    }

    #[Test]
    public function every_exportable_table_has_a_key_to_page_by_on_mysql(): void
    {
        $this->assertEveryExportableTableCanBePagedByKey();
    }
}
