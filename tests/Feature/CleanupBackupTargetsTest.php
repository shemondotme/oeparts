<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\CleanupRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Cleanup\CleanupManager;
use App\Services\Updates\UpdateWatchdog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Backup retention + failed-backup reclaim under the engine, and the shared lock's behaviour towards a
 * long-running cleanup.
 */
class CleanupBackupTargetsTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'backup.disk' => 'local', 'backup.staging_disk' => 'local',
            'backup.retention' => ['daily' => 2, 'weekly' => 0, 'monthly' => 0],
            'cleanup.sleep_ms' => 0,
        ]);

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-cleanupbk-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state, 'updates.root_path' => $this->state.'-root']);
        @mkdir($this->state.'-root', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->state.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->state);
        @rmdir($this->state.'-root');
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function backup(Carbon $finished, string $status = BackupRun::STATUS_SUCCESS, array $meta = [], int $bytes = 1000): BackupRun
    {
        $run = BackupRun::create([
            'profile' => 'full', 'status' => $status, 'trigger' => 'scheduled', 'disk' => 'local',
            'started_at' => $finished, 'finished_at' => $finished, 'total_bytes' => $bytes, 'meta' => $meta,
        ]);
        $path = 'backups/'.$run->id.'/db/part.enc';
        Storage::disk('local')->put($path, 'payload');
        $run->parts()->create(['type' => 'db', 'sequence' => 0, 'disk' => 'local', 'path' => $path, 'bytes' => 7]);

        return $run;
    }

    private function clean(string $key, string $mode = CleanupRun::MODE_EXECUTE): CleanupRun
    {
        $manager = app(CleanupManager::class);

        return $manager->run($manager->start([$key], $mode), 120, 10);
    }

    #[Test]
    public function the_preview_names_exactly_what_the_real_retention_would_delete_and_deletes_nothing(): void
    {
        $keep1 = $this->backup(now()->setTime(12, 0));
        $keep2 = $this->backup(now()->subDay()->setTime(12, 0));
        $old1 = $this->backup(now()->subDays(5)->setTime(12, 0), bytes: 5000);
        $old2 = $this->backup(now()->subDays(6)->setTime(12, 0), bytes: 7000);

        $preview = $this->clean('backup_retention', CleanupRun::MODE_DRY_RUN);

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $preview->status);
        $this->assertSame(2, $preview->targeted);
        $this->assertSame(12000, $preview->items()->first()->bytes_freed_apparent, 'sum of the backups that would go');
        Storage::disk('local')->assertExists('backups/'.$old1->id.'/db/part.enc');
        $this->assertNull($old1->refresh()->meta['pruned_at'] ?? null);
    }

    #[Test]
    public function running_retention_deletes_the_files_marks_the_runs_pruned_and_verifies(): void
    {
        $keep = $this->backup(now()->setTime(12, 0));
        $this->backup(now()->subDay()->setTime(12, 0));
        $old = $this->backup(now()->subDays(5)->setTime(12, 0), bytes: 5000);

        $run = $this->clean('backup_retention');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, json_encode($run->items()->first()->reasons));
        $this->assertSame(1, $run->deleted);
        $this->assertSame(0, $run->remaining);
        Storage::disk('local')->assertMissing('backups/'.$old->id.'/db/part.enc');
        Storage::disk('local')->assertExists('backups/'.$keep->id.'/db/part.enc');
        $this->assertNotNull($old->refresh()->meta['pruned_at']);
        $this->assertSame(5000, $run->items()->first()->bytes_freed_apparent);
    }

    #[Test]
    public function retention_through_the_engine_still_protects_the_backup_an_update_could_roll_back_to(): void
    {
        $this->backup(now()->setTime(12, 0));
        $this->backup(now()->subDay()->setTime(12, 0));
        $rollback = $this->backup(now()->subDays(9)->setTime(12, 0));
        UpdateHistory::create(['from_version' => '1', 'to_version' => '2', 'status' => UpdateHistory::STATUS_SUCCESS, 'backup_run_id' => $rollback->id]);

        $run = $this->clean('backup_retention');

        $this->assertSame(0, $run->targeted);
        Storage::disk('local')->assertExists('backups/'.$rollback->id.'/db/part.enc');
    }

    #[Test]
    public function failed_backups_still_holding_files_are_reclaimed_and_a_healthy_running_one_is_not(): void
    {
        $failed = $this->backup(now()->subDays(2), BackupRun::STATUS_FAILED, [], 4000);
        $healthy = $this->backup(now(), BackupRun::STATUS_RUNNING);
        $healthy->forceFill(['status' => BackupRun::STATUS_RUNNING])->save();
        DB::table('backup_runs')->where('id', $healthy->id)->update(['updated_at' => now()]); // moved a moment ago

        $preview = $this->clean('backup_partials', CleanupRun::MODE_DRY_RUN);
        $this->assertSame(1, $preview->targeted);
        $this->assertSame(4000, $preview->items()->first()->bytes_freed_apparent);

        // A healthy running backup makes the system busy for any REAL run; finish it first (it is not what we are testing).
        $healthy->update(['status' => BackupRun::STATUS_SUCCESS]);

        $run = $this->clean('backup_partials');

        $this->assertSame(CleanupRun::STATUS_SUCCESS, $run->status, json_encode($run->items()->first()->reasons));
        $this->assertSame(1, $run->deleted);
        Storage::disk('local')->assertMissing('backups/'.$failed->id.'/db/part.enc');
        $this->assertNotNull($failed->refresh()->meta['cleaned_at']);
    }

    /* ---- the shared lock ------------------------------------------------ */

    #[Test]
    public function the_janitors_do_not_release_a_lock_held_by_a_cleanup_that_is_still_alive(): void
    {
        $run = CleanupRun::create(['status' => CleanupRun::STATUS_RUNNING, 'active_key' => 'cleanup']);
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode(['owner' => $run->lockOwner(), 'acquired_at' => now()->subHours(3)->toIso8601String()]));

        app(BackupJanitor::class)->cleanupPartials();
        app(UpdateWatchdog::class)->reclaimStale();

        $this->assertTrue($lock->isLocked(), 'a cleanup that takes longer than the stale threshold but keeps its heartbeat fresh keeps its lock');
        $this->assertTrue($lock->heldByLiveCleanup(3600));
    }

    #[Test]
    public function the_janitors_do_release_the_lock_of_a_cleanup_that_stopped_moving(): void
    {
        $run = CleanupRun::create(['status' => CleanupRun::STATUS_RUNNING, 'active_key' => 'cleanup']);
        CleanupRun::whereKey($run->id)->update(['updated_at' => now()->subHours(3)]);
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode(['owner' => $run->lockOwner(), 'acquired_at' => now()->subHours(3)->toIso8601String()]));

        app(BackupJanitor::class)->cleanupPartials();

        $this->assertFalse($lock->isLocked(), 'a dead cleanup must not block backups and updates forever');
    }

    #[Test]
    public function a_backup_or_update_lock_is_not_mistaken_for_a_cleanup_lock(): void
    {
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode(['owner' => 'backup:3', 'acquired_at' => now()->subHours(3)->toIso8601String()]));

        $this->assertFalse($lock->heldByLiveCleanup(3600));
        app(BackupJanitor::class)->cleanupPartials();
        $this->assertFalse($lock->isLocked(), 'the existing stale-lock behaviour is unchanged for backups');
    }
}
