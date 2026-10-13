<?php

namespace Tests\Mysql;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Base of the suite that runs on a real MySQL or MariaDB server (phpunit.mysql.xml).
 *
 * It creates the whole schema ONCE per process with the real migrations, so tests see exactly what
 * production sees (every foreign key, every index, every collation). Because that means dropping all
 * tables, it first proves it is pointed at a throw-away database: the name must end in
 * `_migration_test`. Anything else aborts before a single statement runs.
 *
 * Tests here that change data clean up after themselves (they run in one schema, not one per test).
 */
abstract class MysqlTestCase extends TestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertThrowAwayDatabase();

        if (! self::$schemaReady) {
            // The 176 migrations take minutes on a real server. A schema that already holds every migration of the
            // working tree is reused (the tests here clean up after themselves); MIGRATION_TEST_REFRESH=1 forces a rebuild.
            if (getenv('MIGRATION_TEST_REFRESH') === '1' || ! $this->schemaMatchesWorkingTree()) {
                Artisan::call('migrate:fresh', ['--force' => true]);
            }

            self::$schemaReady = true;
        }
    }

    private function schemaMatchesWorkingTree(): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        $files = array_map(static fn (string $path): string => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);
        $applied = DB::table('migrations')->pluck('migration')->all();

        return array_diff($files, $applied) === [] && array_diff($applied, $files) === [];
    }

    protected function isMariaDb(): bool
    {
        return str_contains((string) DB::selectOne('SELECT VERSION() AS v')->v, 'MariaDB');
    }

    private function assertThrowAwayDatabase(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $name = (string) $connection->getDatabaseName();

        if (! in_array($driver, ['mysql', 'mariadb'], true) || ! str_ends_with($name, '_migration_test')) {
            throw new \RuntimeException(
                "The MySQL suite refuses to run on driver '{$driver}', database '{$name}': it drops every table, so the database "
                .'name must end in _migration_test. Run it with: php artisan test -c phpunit.mysql.xml'
            );
        }
    }
}
