<?php

namespace Tests\Mysql;

use App\Services\Migration\Export\FkGraph;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * The authoritative side of the foreign-key snapshot. The fixture is generated HERE, from information_schema
 * on a real server, and the SQLite suite (DependencyResolverTest) must produce the identical list, so SQLite
 * cannot hide a key that a later ALTER TABLE added on MySQL.
 *
 * After classifying a changed or new reference in GroupRegistry, refresh the snapshot with:
 *
 *     REGENERATE_FK_FIXTURE=1 docker compose exec -T laravel.test php artisan test -c phpunit.mysql.xml --filter=ForeignKeyFixtureTest
 */
class ForeignKeyFixtureTest extends MysqlTestCase
{
    private const FIXTURE = 'tests/Fixtures/migration/fk-edges.json';

    #[Test]
    public function the_graph_read_from_information_schema_equals_the_committed_snapshot(): void
    {
        $signatures = (new FkGraph)->signatures();

        if (getenv('REGENERATE_FK_FIXTURE') === '1') {
            $previous = json_decode((string) file_get_contents(base_path(self::FIXTURE)), true);
            file_put_contents(base_path(self::FIXTURE), json_encode(['_comment' => $previous['_comment'], 'edges' => $signatures], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->markTestSkipped('The foreign-key snapshot was regenerated.');
        }

        $fixture = json_decode((string) file_get_contents(base_path(self::FIXTURE)), true, 8, JSON_THROW_ON_ERROR);

        $this->assertSame($fixture['edges'], $signatures);
    }

    #[Test]
    public function no_foreign_key_is_missing_from_the_graph_that_information_schema_reports(): void
    {
        $count = (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM (SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_TABLE_NAME <> TABLE_NAME) k'
        )->c;

        $this->assertSame($count, count((new FkGraph)->edges()), 'every constraint except a self-reference is an edge');
    }
}
