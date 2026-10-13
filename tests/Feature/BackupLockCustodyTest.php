<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\CleanupRun;
use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Backup\LockCustody;
use App\Services\Migration\Export\FreezeState;
use App\Services\Updates\UpdateWatchdog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.7b: who may release the shared backup/update lock. An export holds it for hours on a big site,
 * far beyond the generic stale thresholds, so each kind of owner is judged by its own rule and a dead
 * export does not pin the lock for ever. These tests drive both janitors with real lock files.
 */
class BackupLockCustodyTest extends TestCase
{
    use RefreshDatabase;

    private BackupLock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lock = app(BackupLock::class);
        $this->lock->release();
        $this->clearFreeze();
        config(['updates.stale_after_seconds' => 7200, 'backup.stale_after_seconds' => 3600, 'migration.export.heartbeat_stale_seconds' => 1800]);
    }

    protected function tearDown(): void
    {
        $this->lock->release();
        $this->clearFreeze();
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function clearFreeze(): void
    {
        $file = FreezeState::file()->path();
        foreach ([$file, $file.'.prev', $file.'.lock'] as $path) {
            @unlink($path);
        }
    }

    private function holdLock(string $owner, \DateTimeInterface $since): void
    {
        file_put_contents($this->lock->path(), json_encode(['owner' => $owner, 'acquired_at' => $since->format(DATE_ATOM)]));
    }

    private function exportRun(string $status, int $idleSeconds): BackupRun
    {
        $run = BackupRun::create(['profile' => BackupRun::PROFILE_EXPORT, 'status' => $status, 'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local']);
        BackupRun::whereKey($run->id)->update(['updated_at' => now()->subSeconds($idleSeconds)]);

        return $run;
    }

    private function runBothJanitors(): void
    {
        app(UpdateWatchdog::class)->reclaimStale();
        app(BackupJanitor::class)->cleanupPartials();
    }

    // ---- an export is judged by its own horizon -------------------------------------------------

    #[Test]
    public function an_export_lock_older_than_the_generic_threshold_survives_both_janitors_while_its_run_moves(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_RUNNING, 1700);
        $this->holdLock($run->lockOwner(), now()->subHours(3));

        $this->runBothJanitors();

        $this->assertTrue($this->lock->isLocked(), 'a run advanced 1700 s ago is inside the 1800 s horizon');
        $this->assertTrue($this->lock->heldByLiveExport());
    }

    #[Test]
    public function the_same_lock_is_released_once_the_run_has_been_silent_past_the_horizon(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_RUNNING, 1900);
        $this->holdLock($run->lockOwner(), now()->subHours(3));

        $this->assertFalse($this->lock->heldByLiveExport());

        $this->runBothJanitors();

        $this->assertFalse($this->lock->isLocked());
    }

    #[Test]
    public function a_pending_export_that_is_moving_also_keeps_the_lock(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_PENDING, 60);
        $this->holdLock($run->lockOwner(), now()->subHours(3));

        $this->runBothJanitors();

        $this->assertTrue($this->lock->isLocked());
    }

    #[Test]
    public function a_finished_or_failed_export_no_longer_holds_the_lock(): void
    {
        foreach ([BackupRun::STATUS_SUCCESS, BackupRun::STATUS_FAILED] as $status) {
            $run = $this->exportRun($status, 10);
            $this->holdLock($run->lockOwner(), now()->subHours(3));

            $this->assertFalse($this->lock->heldByLiveExport(), $status);
        }
    }

    #[Test]
    public function an_export_lock_with_no_run_row_at_all_is_dead(): void
    {
        $this->holdLock('export:9999', now()->subHours(3));

        $this->assertFalse($this->lock->heldByLiveExport());

        $this->runBothJanitors();
        $this->assertFalse($this->lock->isLocked());
    }

    #[Test]
    public function the_horizon_is_the_configured_one_and_not_the_janitors(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_RUNNING, 3000);
        $this->holdLock($run->lockOwner(), now()->subHours(3));

        $this->assertFalse($this->lock->heldByLiveExport());

        config(['migration.export.heartbeat_stale_seconds' => 3600]);
        $this->assertTrue($this->lock->heldByLiveExport());
    }

    // ---- a freeze keeps the lock until the freeze ends -------------------------------------------

    #[Test]
    public function a_frozen_export_whose_driver_died_keeps_the_lock_until_the_freeze_deadline(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_RUNNING, 5000); // silent for far longer than the horizon
        $this->holdLock($run->lockOwner(), now()->subHours(3));
        FreezeState::begin($run->id, now()->subMinutes(100), now()->addMinutes(80), ['present' => true, 'enabled' => 0])->write();

        $this->runBothJanitors();
        $this->assertTrue($this->lock->isLocked(), 'the shop is still frozen: the lock stays');

        Carbon::setTestNow(now()->addMinutes(81));
        $this->runBothJanitors();
        $this->assertFalse($this->lock->isLocked(), 'once the freeze has ended the dead export loses the lock');
    }

    #[Test]
    public function the_hold_deadline_governs_after_the_package_is_sealed(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_SUCCESS, 5000);
        $this->holdLock($run->lockOwner(), now()->subHours(3));
        FreezeState::begin($run->id, now()->subMinutes(200), now()->subMinutes(20), ['present' => true, 'enabled' => 0])
            ->toHold(now()->addMinutes(60))->write();

        $this->assertTrue($this->lock->heldByLiveExport(), 'the export cap passed but the hold has an hour left');

        Carbon::setTestNow(now()->addMinutes(61));
        $this->assertFalse($this->lock->heldByLiveExport());
    }

    #[Test]
    public function a_freeze_that_belongs_to_another_run_does_not_protect_this_lock(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_RUNNING, 5000);
        $this->holdLock($run->lockOwner(), now()->subHours(3));
        FreezeState::begin($run->id + 100, now(), now()->addHours(2), ['present' => false, 'enabled' => null])->write();

        $this->assertFalse($this->lock->heldByLiveExport());
    }

    #[Test]
    public function a_released_freeze_does_not_protect_the_lock(): void
    {
        $run = $this->exportRun(BackupRun::STATUS_RUNNING, 5000);
        $this->holdLock($run->lockOwner(), now()->subHours(3));
        FreezeState::begin($run->id, now(), now()->addHours(2), ['present' => false, 'enabled' => null])->released(now())->write();

        $this->assertFalse($this->lock->heldByLiveExport());
    }

    // ---- everything that is not an export keeps the old rule ------------------------------------

    #[Test]
    public function an_owner_of_an_unknown_kind_is_reclaimed_by_the_old_age_rule(): void
    {
        $this->holdLock('mystery:1', now()->subHours(3));

        $this->assertFalse($this->lock->heldByLiveOwner(3600));

        $this->runBothJanitors();
        $this->assertFalse($this->lock->isLocked());

        $this->holdLock('mystery:1', now()->subMinutes(10));
        $this->runBothJanitors();
        $this->assertTrue($this->lock->isLocked(), 'a young lock is left alone');
    }

    #[Test]
    public function an_import_lock_is_not_yet_protected_and_follows_the_old_rule(): void
    {
        $this->holdLock('import:abc', now()->subHours(3));

        $this->assertFalse($this->lock->heldByLiveImport());

        $this->runBothJanitors();
        $this->assertFalse($this->lock->isLocked());
    }

    #[Test]
    public function a_cleanup_that_keeps_moving_still_keeps_its_lock(): void
    {
        $cleanup = CleanupRun::create(['status' => CleanupRun::STATUS_RUNNING, 'active_key' => 'cleanup']);
        $this->holdLock($cleanup->lockOwner(), now()->subHours(3));

        $this->assertTrue($this->lock->heldByLiveOwner(3600));

        $this->runBothJanitors();
        $this->assertTrue($this->lock->isLocked());
    }

    #[Test]
    public function a_backup_lock_is_never_live_by_this_rule_so_the_row_guard_decides(): void
    {
        $this->holdLock('backup:1', now()->subHours(3));

        $this->assertFalse($this->lock->heldByLiveOwner(3600));
    }

    // ---- ensureHeld ----------------------------------------------------------------------------

    #[Test]
    public function an_owner_that_still_holds_the_lock_carries_on(): void
    {
        $this->holdLock('export:5', now());

        $this->assertSame(LockCustody::Held, $this->lock->ensureHeld('export:5'));
    }

    #[Test]
    public function an_owner_whose_lock_was_released_while_it_idled_takes_it_again(): void
    {
        $this->assertSame(LockCustody::Reacquired, $this->lock->ensureHeld('export:5'));
        $this->assertSame('export:5', $this->lock->owner()['owner']);
    }

    #[Test]
    public function an_owner_whose_lock_was_taken_by_someone_else_has_lost_it_and_changes_nothing(): void
    {
        $this->holdLock('backup:9', now());

        $this->assertSame(LockCustody::Lost, $this->lock->ensureHeld('export:5'));
        $this->assertSame('backup:9', $this->lock->owner()['owner'], 'the other holder is untouched');
    }

    #[Test]
    public function an_unreadable_lock_file_is_not_taken_over(): void
    {
        file_put_contents($this->lock->path(), 'not json');

        $this->assertSame(LockCustody::Lost, $this->lock->ensureHeld('export:5'));
    }
}
