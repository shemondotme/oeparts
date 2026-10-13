<?php

namespace Tests\Feature;

use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\ExportEstimator;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Export\Transform\PiiMap;
use App\Services\Migration\Export\Transform\Pseudonymizer;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesExportStage;
use Tests\TestCase;

/**
 * Phase 3.13: the proof behind the word "scrambled". The audit does not trust the anonymiser's own map: it
 * finds the columns from the live schema, plants a canary string in every text, varchar and JSON column of
 * every personal table, exports with the staging rules and reads every exported row back. A canary may
 * survive only in a column the package report openly lists as kept, and every column listed as kept must
 * really have survived (a list that promises more than it does is as wrong as one that hides something).
 */
class StagingAnonymiserAuditTest extends TestCase
{
    use DrivesExportStage;
    use RefreshDatabase;

    private const SALT = 'f3a9c1d27b5e4086a1d2c3b4e5f60718f3a9c1d27b5e4086a1d2c3b4e5f60718';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exportstage');
        config(['backup.staging_disk' => 'exportstage', 'migration.export.batch_seconds' => 0, 'migration.export.page_rows' => 50]);
    }

    /** @return array<int, string> */
    private function personalTables(): array
    {
        $tables = array_merge(PiiMap::tables(), array_keys(PiiMap::notScrubbed()));
        $tables = array_values(array_unique(array_diff($tables, ['settings']))); // settings holds the shop's own configuration, covered by its own transformers

        sort($tables);

        return $tables;
    }

    private function isTextual(array $column): bool
    {
        return in_array((string) $column['type_name'], ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'json'], true)
            && ! $this->enumValues($column);
    }

    /** @return array<int, string>|null */
    private function enumValues(array $column): ?array
    {
        if (preg_match('/^enum\\((.+)\\)$/i', (string) $column['type'], $m) === 1) {
            return array_map(static fn (string $v): string => trim($v, "'"), explode(',', $m[1]));
        }

        return null;
    }

    /** SQLite stores an enum as a varchar with a CHECK; the allowed values are only in the table's SQL. */
    private function sqliteEnum(string $table, string $column): ?array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return null;
        }

        $sql = (string) DB::table('sqlite_master')->where('name', $table)->value('sql');

        if (preg_match('/"'.preg_quote($column, '/').'"\s+in\s*\(([^)]*)\)/i', $sql, $m) === 1) {
            return array_map(static fn (string $v): string => trim(trim($v), "'\""), explode(',', $m[1]));
        }

        return null;
    }

    private function maxLength(array $column): ?int
    {
        return preg_match('/\((\d+)\)/', (string) $column['type'], $m) === 1 ? (int) $m[1] : null;
    }

    /** One row whose every textual column carries a canary that names its own table and column. */
    private function plant(string $table): void
    {
        $row = [];

        foreach (Schema::getColumns($table) as $column) {
            $name = (string) $column['name'];
            $type = (string) $column['type_name'];
            $enum = $this->enumValues($column) ?? $this->sqliteEnum($table, $name);

            if ($enum !== null && $enum !== []) {
                $row[$name] = $enum[0];
            } elseif ($name === 'id') {
                $row[$name] = 1;
            } elseif (in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'boolean', 'decimal', 'float', 'double'], true)) {
                $row[$name] = $column['nullable'] && str_ends_with($name, '_id') ? null : 1;
            } elseif (in_array($type, ['timestamp', 'datetime', 'date'], true)) {
                $row[$name] = now()->toDateTimeString();
            } elseif ($this->isTextual($column)) {
                $canary = "CANARY-PII-{$table}.{$name}-PADDING";
                $limit = $this->maxLength($column);
                $value = ($limit !== null && $limit < strlen($canary)) ? 'ZZ' : $canary;
                $row[$name] = $type === 'json' ? json_encode(['c' => $value]) : $value;
            } else {
                $row[$name] = $column['nullable'] ? null : '1';
            }
        }

        DB::table($table)->insert($row);
    }

    // ---- the maps are about real columns --------------------------------------------------------------

    #[Test]
    public function every_rule_and_every_kept_column_names_a_real_column(): void
    {
        foreach (PiiMap::rules() as $table => $rules) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is on the personal-data map but is not a table");

            foreach (array_keys($rules) as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column} is on the personal-data map but is not a column");
            }
        }

        foreach ([...PiiMap::notScrubbedColumns(), ...PiiMap::structuralColumns()] as $tableColumn) {
            [$table, $column] = explode('.', $tableColumn);
            $this->assertTrue(Schema::hasColumn($table, $column), "{$tableColumn} is listed as kept but is not a column");
        }
    }

    #[Test]
    public function a_column_that_looks_personal_is_either_scrambled_or_openly_listed_as_kept(): void
    {
        $plan = $this->stagingPlan();
        $looksPersonal = '/mail|phone|ip_address|first_name|last_name|address_line|postal|vin_number|message|comment|reason|^notes?$|_notes?$|customer|billing_(?!country)|shipping_name|reviewer|company|vat_number|user_agent|token/i';
        $covered = PiiMap::notScrubbedColumns();
        foreach (PiiMap::rules() as $table => $rules) {
            foreach (array_keys($rules) as $column) {
                $covered[] = "{$table}.{$column}";
            }
        }

        $uncovered = [];

        foreach ($plan->tables as $table) {
            foreach (Schema::getColumns($table) as $column) {
                if ($this->isTextual($column) && preg_match($looksPersonal, (string) $column['name']) === 1 && ! in_array("{$table}.{$column['name']}", $covered, true)) {
                    $uncovered[] = "{$table}.{$column['name']}";
                }
            }
        }

        $this->assertSame([], $uncovered, 'These look like personal data, are exported in a staging copy, and are neither scrambled nor listed as kept. Decide each one in PiiMap.');
    }

    #[Test]
    public function the_staging_preset_scrambles_and_keeps_every_personal_table_in_the_copy(): void
    {
        $plan = $this->stagingPlan();

        $this->assertTrue($plan->anonymise);
        $this->assertFalse($plan->includeSecrets);
        $this->assertSame([], array_values(array_diff(PiiMap::tables(), $plan->tables)), 'a personal table has fallen out of the staging copy: the map names a table nobody exports, or a table moved group');
        $this->assertSame([], array_values(array_intersect($plan->tables, ['activity_logs', 'login_logs', 'email_logs', 'search_logs', 'custom_invoices', 'invoice_clients'])), 'logs, carts and invoices stay out of a scrambled copy');
    }

    // ---- the canary proof -----------------------------------------------------------------------------

    #[Test]
    public function no_canary_survives_the_staging_export_except_in_the_columns_listed_as_kept(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON'); // rolled back with the test, never checked

        $tables = $this->personalTables();
        foreach ($tables as $table) {
            $this->plant($table);
        }

        $run = $this->run_($this->planOf($tables, ['anonymise' => true, 'include_secrets' => false]), ['anonymise_salt' => self::SALT]);
        $this->drive($run);

        $survived = [];
        $seenTables = [];

        foreach ($tables as $table) {
            $exported = $this->exported($run, $table);
            $this->assertNotEmpty($exported['rows'], "{$table} exported nothing");
            $seenTables[] = $table;

            preg_match_all('/CANARY-PII-([a-z_0-9]+\.[a-z_0-9]+)-PADDING/', (string) json_encode($exported['rows']), $m);
            $survived = array_merge($survived, $m[1]);
        }

        $survived = array_values(array_unique($survived));
        sort($survived);

        $allowed = array_values(array_filter([...PiiMap::notScrubbedColumns(), ...PiiMap::structuralColumns()], static fn (string $c): bool => ! str_starts_with($c, 'settings.')));

        $leaked = array_values(array_diff($survived, $allowed));
        $this->assertSame([], $leaked, 'personal data survived in a staging export');

        $promisedButGone = array_values(array_diff(array_filter($allowed, fn (string $c): bool => $this->canaryWasPlanted($c)), $survived));
        $this->assertSame([], $promisedButGone, 'listed as kept, but actually changed: the list and the behaviour disagree');
    }

    private function canaryWasPlanted(string $tableColumn): bool
    {
        [$table, $name] = explode('.', $tableColumn);

        foreach (Schema::getColumns($table) as $column) {
            if ($column['name'] === $name) {
                $limit = $this->maxLength($column);

                return $this->isTextual($column) && ($limit === null || $limit >= strlen("CANARY-PII-{$tableColumn}-PADDING"));
            }
        }

        return false;
    }

    #[Test]
    public function the_per_export_salt_is_never_written_into_the_staged_parts(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $this->plant('users');

        $run = $this->run_($this->planOf(['users'], ['anonymise' => true, 'include_secrets' => false]), ['anonymise_salt' => self::SALT]);
        $this->drive($run);

        foreach (Storage::disk('exportstage')->allFiles('backups/'.$run->id) as $file) {
            $this->assertStringNotContainsString(self::SALT, (string) Storage::disk('exportstage')->get($file));
        }
    }

    #[Test]
    public function one_person_is_one_stand_in_across_tables_and_a_unique_column_stays_unique(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $now = now()->toDateTimeString();

        foreach ([1 => 'anna@example.com', 2 => 'bob@example.com'] as $id => $email) {
            DB::table('users')->insert(['id' => $id, 'name' => "Customer {$id}", 'email' => $email, 'password' => 'x', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('newsletter_subscribers')->insert(['id' => 1, 'email' => 'anna@example.com', 'lang' => 'en', 'is_active' => 1, 'subscribed_at' => $now, 'ip_address' => '203.0.113.5']);

        $run = $this->run_($this->planOf(['users', 'newsletter_subscribers'], ['anonymise' => true, 'include_secrets' => false]), ['anonymise_salt' => self::SALT]);
        $this->drive($run);

        $users = $this->exported($run, 'users');
        $header = $users['headers'][0]['columns'] ?? array_keys($users['headers'][0]);
        $emailAt = array_search('email', $header, true);
        $emails = array_column($users['rows'], $emailAt);

        $this->assertCount(2, array_unique($emails));
        $this->assertSame((new Pseudonymizer(self::SALT))->email('anna@example.com'), $emails[0]);

        $subs = $this->exported($run, 'newsletter_subscribers');
        $subHeader = $subs['headers'][0]['columns'] ?? array_keys($subs['headers'][0]);
        $this->assertSame($emails[0], $subs['rows'][0][array_search('email', $subHeader, true)], 'the same address is the same stand-in in every table');
    }

    private function stagingPlan(): ExportPlan
    {
        $registry = new GroupRegistry;

        return (new ExportPlanner(
            $registry, new DependencyResolver($registry, new FkGraph), new LogicalRefRegistry($registry), new ExportEstimator,
            fn (): int => PHP_INT_MAX, null, fn (): int => 1_800_000_000
        ))->plan(Preset::Staging, new ExportOptions);
    }
}
