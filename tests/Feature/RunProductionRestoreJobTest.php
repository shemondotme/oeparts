<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Jobs\RunProductionRestoreJob;
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
 * Phase 13 (Backup/Update System Deep Verification). ProductionRestoreService
 * itself is well covered by ProductionRestoreServiceTest, but every one of
 * those tests calls the service directly — the job WRAPPER's own logic
 * (missing-run handling, the catch-log-notify-rethrow shape, $tries) had no
 * coverage at all. $tries=1 (this phase's own fix) matters here specifically
 * because this is the DESTRUCTIVE, live-swap restore, unlike the sibling
 * RestoreBackupJob (files-only, into a side directory, safe to retry) it
 * otherwise mirrors — a queue-worker timeout kill happens well before
 * ProductionRestoreService::restore()'s own cleanup can run, so an auto-retry
 * would find BackupLock still held and fail fast with a misleading "already
 * in progress" error instead of the real timeout, up to 3 times over.
 */
class RunProductionRestoreJobTest extends TestCase
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

        $this->statePath = storage_path('app/oe-prodrestorejob-state-'.getmypid());
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local', 'backup.db.chunk_rows' => 100]);

        $this->backupFixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-prodrestorejob-fixture-'.getmypid();
        @mkdir($this->backupFixture.'/app', 0775, true);
        file_put_contents($this->backupFixture.'/app/Marker.php', '<?php // restored-version');
        config(['backup.files.root' => $this->backupFixture]);

        $this->liveRoot = storage_path('app/oe-prodrestorejob-live-'.getmypid());
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
    public function it_declares_a_single_try_so_a_worker_timeout_never_silently_retries_the_destructive_swap(): void
    {
        $this->assertSame(1, (new RunProductionRestoreJob(1))->tries);
    }

    #[Test]
    public function it_delegates_to_the_service_and_completes_the_restore(): void
    {
        $run = $this->successfulBackupRun();

        (new RunProductionRestoreJob($run->getKey(), requestedBy: 7))
            ->handle(app(ProductionRestoreService::class));

        $history = UpdateHistory::where('restore_of_backup_run_id', $run->getKey())->firstOrFail();
        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->status);
        $this->assertSame(
            '<?php // restored-version',
            file_get_contents($this->liveRoot.'/app/Marker.php')
        );

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === true);
    }

    #[Test]
    public function it_notifies_admins_instead_of_silently_returning_when_the_backup_run_no_longer_exists(): void
    {
        (new RunProductionRestoreJob(999999, requestedBy: 7))
            ->handle(app(ProductionRestoreService::class));

        $this->assertDatabaseCount('update_histories', 0);

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, function ($job) {
            return $job->result['success'] === false
                && $job->result['trigger'] === 'restore'
                && str_contains($job->result['error'], 'no longer exists');
        });
    }

    #[Test]
    public function it_notifies_admins_and_rethrows_when_the_service_cannot_even_start(): void
    {
        $unrestorable = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_FAILED,
            'trigger' => BackupRun::TRIGGER_MANUAL,
        ]);

        $this->expectException(UpdateException::class);

        try {
            (new RunProductionRestoreJob($unrestorable->getKey()))
                ->handle(app(ProductionRestoreService::class));
        } finally {
            Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === false);
        }
    }
}
