<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfBackupFailure;
use App\Models\BackupRun;
use App\Services\Backup\BackupLock;
use App\Services\HealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * oeparts:backup scheduled command + health integration (Module 21, Chunk 2.6).
 */
class RunBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;
    private string $filesRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-runbackup-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);
        config(['backup.db.chunk_rows' => 100]);

        $this->filesRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-runbackup-files-'.getmypid();
        @mkdir($this->filesRoot, 0775, true);
        file_put_contents($this->filesRoot.'/a.txt', 'content');
        config(['backup.files.root' => $this->filesRoot]);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        @array_map('unlink', glob($this->filesRoot.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->filesRoot);
        parent::tearDown();
    }

    #[Test]
    public function it_runs_a_successful_backup_and_logs_a_cron_entry(): void
    {
        $this->artisan('oeparts:backup', ['--profile' => 'full'])->assertSuccessful();

        $this->assertSame(1, BackupRun::query()->successful()->count());
        $this->assertTrue(
            DB::table('cron_logs')
                ->where('job_name', 'oeparts:backup')
                ->where('status', 'success')
                ->exists()
        );
    }

    #[Test]
    public function a_blocked_backup_fails_and_alerts_super_admins(): void
    {
        Queue::fake();

        // Simulate a concurrent backup/update holding the shared lock.
        app(BackupLock::class)->acquire('update:in-progress');

        $this->artisan('oeparts:backup')->assertFailed();

        Queue::assertPushed(NotifyAdminsOfBackupFailure::class);
    }

    #[Test]
    public function the_health_check_reports_the_last_backup_age(): void
    {
        $health = app(HealthCheckService::class);

        $this->assertSame('none', $health->checkLastBackup());
        $this->assertNull($health->lastBackupAt());

        $this->artisan('oeparts:backup')->assertSuccessful();

        $this->assertSame('ok', $health->checkLastBackup());
        $this->assertNotNull($health->lastBackupAt());
    }

    #[Test]
    public function a_stale_backup_is_flagged(): void
    {
        BackupRun::create([
            'profile'     => BackupRun::PROFILE_FULL,
            'status'      => BackupRun::STATUS_SUCCESS,
            'trigger'     => BackupRun::TRIGGER_SCHEDULED,
            'disk'        => 'local',
            'finished_at' => now()->subHours(48),
        ]);

        config(['settings' => []]); // fall back to default 26h threshold
        $this->assertSame('stale', app(HealthCheckService::class)->checkLastBackup());
    }
}
