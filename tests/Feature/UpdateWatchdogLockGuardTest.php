<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Updates\UpdateWatchdog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The backup/update lock is shared. BackupJanitor already refused to release it while a
 * backup run was still touching its row; UpdateWatchdog had no such guard, so a plain
 * full backup that simply ran longer than updates.stale_after_seconds (2 h) lost its
 * lock mid-run and an update, cleanup or second backup could start on top of it.
 */
class UpdateWatchdogLockGuardTest extends TestCase
{
    use RefreshDatabase;

    private BackupLock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lock = app(BackupLock::class);
        $this->lock->release();
    }

    protected function tearDown(): void
    {
        $this->lock->release();

        parent::tearDown();
    }

    private function holdLockSince(\DateTimeInterface $at, string $owner = 'backup:1'): void
    {
        file_put_contents($this->lock->path(), json_encode(['owner' => $owner, 'acquired_at' => $at->format(DATE_ATOM)]));
    }

    private function backupRun(string $status, \DateTimeInterface $touchedAt): BackupRun
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status' => $status,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk' => 'local',
        ]);
        BackupRun::whereKey($run->id)->update(['updated_at' => $touchedAt]);

        return $run;
    }

    #[Test]
    public function the_watchdog_keeps_the_lock_of_a_backup_that_is_still_moving(): void
    {
        config(['updates.stale_after_seconds' => 7200]);
        $this->holdLockSince(now()->subHours(3));
        $this->backupRun(BackupRun::STATUS_RUNNING, now()->subMinutes(2));

        app(UpdateWatchdog::class)->reclaimStale();

        $this->assertTrue($this->lock->isLocked(), 'a backup still touching its row owns the lock, however old the lock is');
    }

    #[Test]
    public function the_watchdog_releases_a_stale_lock_when_no_run_is_active(): void
    {
        config(['updates.stale_after_seconds' => 7200]);
        $this->holdLockSince(now()->subHours(3));

        app(UpdateWatchdog::class)->reclaimStale();

        $this->assertFalse($this->lock->isLocked());
    }

    #[Test]
    public function an_idle_run_past_the_horizon_does_not_protect_the_lock(): void
    {
        config(['updates.stale_after_seconds' => 7200]);
        $this->holdLockSince(now()->subHours(5));
        $this->backupRun(BackupRun::STATUS_RUNNING, now()->subHours(4));

        app(UpdateWatchdog::class)->reclaimStale();

        $this->assertFalse($this->lock->isLocked(), 'a run that stopped moving is dead, so its lock is reclaimable');
    }

    #[Test]
    public function a_finished_run_does_not_protect_the_lock(): void
    {
        config(['updates.stale_after_seconds' => 7200]);
        $this->holdLockSince(now()->subHours(3));
        $this->backupRun(BackupRun::STATUS_SUCCESS, now()->subMinutes(1));

        app(UpdateWatchdog::class)->reclaimStale();

        $this->assertFalse($this->lock->isLocked());
    }

    #[Test]
    public function the_janitor_behaves_the_same_through_the_shared_helper(): void
    {
        config(['backup.stale_after_seconds' => 3600]);
        $this->holdLockSince(now()->subHours(2));
        $run = $this->backupRun(BackupRun::STATUS_RUNNING, now()->subMinutes(1));

        app(BackupJanitor::class)->cleanupPartials();
        $this->assertTrue($this->lock->isLocked(), 'fresh run keeps the lock');

        BackupRun::whereKey($run->id)->update(['updated_at' => now()->subHours(2)]);
        app(BackupJanitor::class)->cleanupPartials();
        $this->assertFalse($this->lock->isLocked(), 'once the run goes quiet the lock is released');
    }

    #[Test]
    public function both_janitors_use_one_shared_helper_and_the_janitor_has_no_private_copy(): void
    {
        $this->assertFalse(method_exists(BackupJanitor::class, 'hasActiveRun'), 'BackupJanitor must not keep its own copy of the active-run check');

        foreach (['Services/Backup/BackupJanitor.php', 'Services/Updates/UpdateWatchdog.php'] as $file) {
            $this->assertStringContainsString('BackupRun::hasFreshActiveRun(', (string) file_get_contents(app_path($file)), $file);
        }
    }
}
