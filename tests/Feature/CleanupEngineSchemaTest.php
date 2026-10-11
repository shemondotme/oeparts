<?php

namespace Tests\Feature;

use App\Models\CleanupDiskSample;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Models\CleanupTargetStat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Cleanup Engine's run ledger. The headline guarantee is the unique
 * `active_key`: it makes "two cleanups can never run at once" a database fact.
 */
class CleanupEngineSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_engine_tables_exist(): void
    {
        foreach (['cleanup_runs', 'cleanup_run_items', 'cleanup_target_stats', 'cleanup_disk_samples'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
    }

    #[Test]
    public function a_new_run_gets_safe_defaults(): void
    {
        $run = CleanupRun::create([])->fresh();

        $this->assertSame(CleanupRun::KIND_SINGLE, $run->kind);
        $this->assertSame(CleanupRun::MODE_EXECUTE, $run->mode);
        $this->assertSame(CleanupRun::STATUS_PENDING, $run->status);
        $this->assertSame(0, $run->percent);
        $this->assertSame(0, $run->targeted);
        $this->assertNull($run->active_key);
        $this->assertFalse($run->isTerminal());
    }

    #[Test]
    public function only_one_run_can_hold_the_active_key(): void
    {
        CleanupRun::create(['status' => CleanupRun::STATUS_RUNNING, 'active_key' => CleanupRun::ACTIVE_KEY]);

        $this->expectException(UniqueConstraintViolationException::class);
        CleanupRun::create(['status' => CleanupRun::STATUS_RUNNING, 'active_key' => CleanupRun::ACTIVE_KEY]);
    }

    #[Test]
    public function finished_runs_release_the_key_so_history_can_hold_many_rows(): void
    {
        // NULLs never collide in a unique index: any number of terminal runs may coexist
        // with exactly one active run.
        CleanupRun::create(['status' => CleanupRun::STATUS_SUCCESS, 'active_key' => null]);
        CleanupRun::create(['status' => CleanupRun::STATUS_FAILED, 'active_key' => null]);
        CleanupRun::create(['status' => CleanupRun::STATUS_RUNNING, 'active_key' => CleanupRun::ACTIVE_KEY]);

        $this->assertSame(3, CleanupRun::count());
        $this->assertSame(1, CleanupRun::active()->count());
        $this->assertSame(2, CleanupRun::terminal()->count());
    }

    #[Test]
    public function terminal_statuses_are_recognised(): void
    {
        foreach (CleanupRun::TERMINAL_STATUSES as $status) {
            $this->assertTrue((new CleanupRun(['status' => $status]))->isTerminal(), $status);
        }
        foreach ([CleanupRun::STATUS_PENDING, CleanupRun::STATUS_RUNNING, CleanupRun::STATUS_PAUSED] as $status) {
            $this->assertFalse((new CleanupRun(['status' => $status]))->isTerminal(), $status);
        }
    }

    #[Test]
    public function the_checkpoint_survives_a_save_and_reload(): void
    {
        $run = CleanupRun::create([]);
        $run->setCheckpoint(['item_index' => 2, 'cursor' => 123456]);
        $run->save();

        $this->assertSame(['item_index' => 2, 'cursor' => 123456], $run->fresh()->checkpoint());
    }

    #[Test]
    public function items_belong_to_a_run_and_go_with_it(): void
    {
        $run = CleanupRun::create([]);
        CleanupRunItem::create(['run_id' => $run->id, 'target' => 'search_logs', 'targeted' => 10, 'deleted' => 10, 'reasons' => ['ok']]);

        $this->assertSame(1, $run->items()->count());
        $this->assertSame(['ok'], $run->items()->first()->reasons);

        $run->delete();
        $this->assertSame(0, CleanupRunItem::count());
    }

    #[Test]
    public function stats_and_disk_samples_round_trip(): void
    {
        CleanupTargetStat::create(['target' => 'cron_logs', 'rows_est' => 1_100_000, 'measured_at' => now()]);
        $this->assertSame(1_100_000, CleanupTargetStat::find('cron_logs')->rows_est);

        CleanupDiskSample::create([
            'measured_at' => now(), 'total_bytes' => 100, 'free_bytes' => 40, 'used_bytes' => 60,
            'breakdown' => ['logs' => 5, 'backups' => 30],
        ]);
        $this->assertSame(['logs' => 5, 'backups' => 30], CleanupDiskSample::first()->breakdown);
    }

    #[Test]
    public function the_engine_ledger_is_never_replayed_by_a_database_restore(): void
    {
        $excluded = config('backup.db.exclude_tables_entirely');

        foreach (['cleanup_runs', 'cleanup_run_items', 'cleanup_target_stats', 'cleanup_disk_samples'] as $table) {
            $this->assertContains($table, $excluded, "{$table} is live bookkeeping, like backup_runs");
        }
    }

    #[Test]
    public function the_migration_is_idempotent(): void
    {
        $migration = require database_path('migrations/2026_10_11_000002_create_cleanup_engine_tables.php');

        $migration->up(); // tables already exist (RefreshDatabase ran it) — must be a no-op, not an error

        $this->assertTrue(Schema::hasTable('cleanup_runs'));
    }
}
