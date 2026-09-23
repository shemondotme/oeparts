<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfBackupFailure;
use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Models\BackupRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\ProductionRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Self-service full (files + database) production restore — an intentional
 * downgrade path distinct from BackupDashboard's existing files-only restore
 * (never touches the live install) and from UpdateApplier::rollback() (only
 * reverses the SAME attempt, automatically). Runs the real backup → restore →
 * swap pipeline against fixture directories, same style as BackupRestoreTest.
 */
class ProductionRestoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    private string $backupFixture;

    private string $liveRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        // Also under storage_path() — UpdateSwapper's swap-backup dir lives
        // here and rename()s files in and out of the live root below, so
        // this must be on the same filesystem as that root (see liveRoot's
        // comment).
        $this->statePath = storage_path('app/oe-prodrestore-state-'.getmypid());
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local', 'backup.db.chunk_rows' => 100]);

        // The backup's "files root" — what gets backed up.
        $this->backupFixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-prodrestore-fixture-'.getmypid();
        @mkdir($this->backupFixture.'/app', 0775, true);
        file_put_contents($this->backupFixture.'/app/Marker.php', '<?php // restored-version');
        config(['backup.files.root' => $this->backupFixture]);

        // The "live install" UpdateSwapper swaps into — starts on an OLDER
        // version. Deliberately under storage_path(), NOT sys_get_temp_dir():
        // rename() (what UpdateSwapper uses) requires source and destination
        // on the same filesystem, and ProductionRestoreService's staging dir
        // is storage_path('app/restore/...') — in production root_path
        // defaults to base_path(), so both are naturally on the same
        // filesystem; sys_get_temp_dir() can be a separate tmpfs mount in
        // this Docker test environment, which isn't representative.
        $this->liveRoot = storage_path('app/oe-prodrestore-live-'.getmypid());
        @mkdir($this->liveRoot.'/app', 0775, true);
        file_put_contents($this->liveRoot.'/app/Marker.php', '<?php // live-version');
        config(['updates.root_path' => $this->liveRoot, 'updates.core_paths' => ['app']]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->backupFixture);
        $this->rrmdir($this->liveRoot);
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.DIRECTORY_SEPARATOR.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function successfulBackupRun(): BackupRun
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL, BackupRun::TRIGGER_MANUAL);
        $run = app(BackupManager::class)->run($run);
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        app(BackupLock::class)->release();

        return $run;
    }

    #[Test]
    public function it_swaps_the_backups_files_into_the_live_root_and_records_success(): void
    {
        $run = $this->successfulBackupRun();

        $history = app(ProductionRestoreService::class)->restore($run, initiatedBy: 7);

        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->status);
        $this->assertSame(UpdateHistory::TYPE_RESTORE, $history->type);
        $this->assertSame($run->getKey(), $history->restore_of_backup_run_id);
        $this->assertSame(7, $history->initiated_by);
        $this->assertNotNull($history->meta['safety_backup_run_id'] ?? null, 'a pre-restore safety backup was taken');

        $this->assertSame(
            '<?php // restored-version',
            file_get_contents($this->liveRoot.'/app/Marker.php'),
            'the live install now has the backup\'s files'
        );

        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertFalse((bool) settings('maintenance.enabled'));

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === true && $job->result['trigger'] === 'restore'
        );
    }

    #[Test]
    public function it_refuses_to_restore_an_unsuccessful_run(): void
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_FAILED,
            'trigger' => BackupRun::TRIGGER_MANUAL,
        ]);

        $this->expectException(UpdateException::class);

        app(ProductionRestoreService::class)->restore($run);
    }

    #[Test]
    public function it_refuses_to_restore_a_pruned_run(): void
    {
        $run = $this->successfulBackupRun();
        $run->meta = array_merge($run->meta ?? [], ['pruned_at' => now()->toIso8601String()]);
        $run->save();

        $this->expectException(UpdateException::class);

        app(ProductionRestoreService::class)->restore($run);
    }

    /**
     * Pre-Phase-22 backlog sweep (2026-09-23). isRestorable() only checks
     * status/pruned — not whether the backup actually HAS both database and
     * file parts, which only a PROFILE_FULL run guarantees. Before this
     * guard, restoring a files_only backup here silently skipped the
     * database entirely (RestoreManager::restoreDatabase() treats "no DB
     * parts" as a warning, never an error, and this method only ever
     * inspected $report->errors) while still returning a plain SUCCESS — an
     * admin doing an emergency full restore would believe the database was
     * rolled back too when it never was.
     */
    #[Test]
    public function it_refuses_to_restore_a_files_only_backup(): void
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FILES_ONLY, BackupRun::TRIGGER_MANUAL);
        $run = app(BackupManager::class)->run($run);
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        app(BackupLock::class)->release();

        try {
            app(ProductionRestoreService::class)->restore($run);
            $this->fail('Expected an UpdateException to be thrown.');
        } catch (UpdateException $e) {
            $this->assertSame('Only a full (files + database) backup can be restored into production.', $e->getMessage());
        }

        // Never got far enough to touch the live files.
        $this->assertSame(
            '<?php // live-version',
            file_get_contents($this->liveRoot.'/app/Marker.php')
        );
    }

    #[Test]
    public function it_refuses_to_restore_a_database_only_backup(): void
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_DATABASE_ONLY, BackupRun::TRIGGER_MANUAL);
        $run = app(BackupManager::class)->run($run);
        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        app(BackupLock::class)->release();

        $this->expectException(UpdateException::class);

        app(ProductionRestoreService::class)->restore($run);
    }

    #[Test]
    public function it_releases_the_lock_and_notifies_admins_when_the_pre_restore_safety_backup_fails(): void
    {
        $run = $this->successfulBackupRun();

        // Force the safety backup to fail: an unknown profile trips
        // BackupManager::start()'s own validation, mirroring how
        // UpdateApplierTest forces a backup failure elsewhere in this suite.
        app()->bind(BackupManager::class, function () {
            return new class extends BackupManager
            {
                public function __construct() {}

                public function start(string $profile = BackupRun::PROFILE_FULL, string $trigger = BackupRun::TRIGGER_MANUAL, array $meta = [], bool $acquireLock = true): BackupRun
                {
                    $r = new BackupRun(['profile' => BackupRun::PROFILE_UPDATE_SAFETY, 'status' => BackupRun::STATUS_FAILED, 'error' => 'simulated safety-backup failure']);
                    $r->id = 12345;

                    return $r;
                }

                public function run(BackupRun $run): BackupRun
                {
                    return $run;
                }
            };
        });

        $history = app(ProductionRestoreService::class)->restore($run, initiatedBy: 7);

        $this->assertSame(UpdateHistory::STATUS_FAILED, $history->status);
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertFalse((bool) settings('maintenance.enabled'));

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === false);
        Queue::assertPushed(NotifyAdminsOfBackupFailure::class, fn ($job) => $job->reason === 'simulated safety-backup failure');
    }
}
