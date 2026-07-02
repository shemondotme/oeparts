<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update System (Module 21) — Chunk 0.2 schema.
 * Verifies the three tables exist with their expected columns, and that the
 * migrations honour CLAUDE.md rule #42: idempotent up() (safe to re-run) and
 * reversible down().
 */
class UpdateSystemSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIONS = [
        'update_histories' => '2026_07_02_100001_create_update_histories_table.php',
        'backup_runs'      => '2026_07_02_100002_create_backup_runs_table.php',
        'backup_parts'     => '2026_07_02_100003_create_backup_parts_table.php',
    ];

    #[Test]
    public function update_histories_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('update_histories'));
        $this->assertTrue(Schema::hasColumns('update_histories', [
            'id', 'from_version', 'to_version', 'channel', 'status', 'step',
            'initiated_by', 'backup_run_id', 'started_at', 'finished_at',
            'error', 'meta', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function backup_runs_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('backup_runs'));
        $this->assertTrue(Schema::hasColumns('backup_runs', [
            'id', 'profile', 'status', 'trigger', 'disk', 'encrypted',
            'app_version', 'php_version', 'db_version', 'total_bytes', 'part_count',
            'manifest_path', 'checksum', 'started_at', 'finished_at', 'expires_at',
            'error', 'meta', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function backup_parts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('backup_parts'));
        $this->assertTrue(Schema::hasColumns('backup_parts', [
            'id', 'backup_run_id', 'type', 'sequence', 'name', 'disk', 'path',
            'sha256', 'bytes', 'rows', 'meta', 'created_at', 'updated_at',
        ]));
    }

    #[Test]
    public function migration_up_is_idempotent(): void
    {
        // Tables already exist (RefreshDatabase). Re-running up() must NOT throw,
        // thanks to the Schema::hasTable() guard (rule #42).
        foreach (self::MIGRATIONS as $table => $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->up();
            $this->assertTrue(Schema::hasTable($table), "up() left {$table} intact");
        }
    }

    #[Test]
    public function migrations_are_reversible(): void
    {
        // Use the leaf table (backup_parts) to avoid the FK-parent constraint.
        $migration = require database_path('migrations/'.self::MIGRATIONS['backup_parts']);

        $migration->down();
        $this->assertFalse(Schema::hasTable('backup_parts'), 'down() drops the table');

        $migration->up();
        $this->assertTrue(Schema::hasTable('backup_parts'), 'up() recreates the table');
    }
}
