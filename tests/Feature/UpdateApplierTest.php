<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfBackupFailure;
use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Models\BackupRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Updates\RecoveryWindowFlag;
use App\Services\Updates\ReleaseSignature;
use App\Services\Updates\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\ReleaseKeys;
use Tests\TestCase;

/**
 * Update apply orchestration FSM (Module 21, Chunk 3.5). Drives the state machine
 * with a fake subclass (steps recorded, no real download/swap) to prove ordering,
 * checkpoint resume, and the failure/rollback matrix; plus the lock-ownership
 * integration with the pre-update backup and the confirm preview.
 */
class UpdateApplierTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-apply-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);

        // complete()/fail() dispatch an admin-notification job — irrelevant
        // to this file's FSM-ordering/rollback assertions, and would
        // otherwise run synchronously (sync queue in tests) and query
        // Admin::role('super_admin'), which needs RolesSeeder this file
        // doesn't run. Notification dispatch itself is covered by
        // UpdateResultNotificationTest.
        Queue::fake();
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->state);
        parent::tearDown();
    }

    private function manifest(array $overrides = []): array
    {
        return array_merge([
            'version' => '1.1.0',
            'channel' => 'stable',
            'size_bytes' => 1024,
            'migration_count' => 2,
            'download_url' => 'https://x/oeparts.zip',
            'sha256' => str_repeat('a', 64),
        ], $overrides);
    }

    #[Test]
    public function it_runs_every_step_in_order_and_succeeds(): void
    {
        $applier = new FakeUpdateApplier;

        $history = $applier->start($this->manifest(), initiatedBy: 7);

        $this->assertSame(UpdateHistory::STATUS_BACKING_UP, $history->status);
        $this->assertSame(7, $history->initiated_by);
        $this->assertTrue(app(BackupLock::class)->isLocked(), 'the updater holds the lock for the whole apply');
        $this->assertTrue((bool) settings('maintenance.enabled'), 'maintenance is on during apply');

        $history = $applier->run($history);

        $this->assertSame(['backup', 'download', 'extract', 'swap', 'finalize', 'verify'], $applier->log);
        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->status);
        $this->assertNotNull($history->finished_at);
        $this->assertFalse(app(BackupLock::class)->isLocked(), 'lock released on success');
        $this->assertFalse((bool) settings('maintenance.enabled'), 'maintenance lifted on success');
    }

    /**
     * exitMaintenance()/disarmRecovery() write through the database — the
     * same kind of failure that can cause an update to need cleanup in the
     * first place can also make the cleanup itself throw. Confirmed live: a
     * DB hiccup during a real update left the BackupLock permanently held,
     * blocking every future backup AND update until the lock file was
     * deleted by hand. complete()/fail()/start() now release the lock in a
     * finally block specifically so this can't happen.
     */
    #[Test]
    public function the_lock_is_released_on_success_even_if_exiting_maintenance_mode_fails(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->throwOnExitMaintenance = true;

        $history = $applier->start($this->manifest());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('simulated DB failure while exiting maintenance mode');

        try {
            $applier->run($history);
        } finally {
            $this->assertFalse(app(BackupLock::class)->isLocked(), 'lock must be released even though exitMaintenance() failed');
        }
    }

    #[Test]
    public function the_lock_is_released_on_failure_even_if_exiting_maintenance_mode_fails(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->failAt = 'download';
        $applier->throwOnExitMaintenance = true;

        $history = $applier->start($this->manifest());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('simulated DB failure while exiting maintenance mode');

        try {
            $applier->run($history);
        } finally {
            $this->assertFalse(app(BackupLock::class)->isLocked(), 'lock must be released even though exitMaintenance() failed');
        }
    }

    #[Test]
    public function the_lock_is_released_when_start_itself_fails_even_if_exiting_maintenance_mode_also_fails(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->throwDuringStart = true;
        $applier->throwOnExitMaintenance = true;

        try {
            $applier->start($this->manifest());
            $this->fail('start() should have thrown its own failure, not the cleanup failure');
        } catch (\RuntimeException $e) {
            // The ORIGINAL failure must surface, not the cleanup failure that
            // happens while handling it — a cleanup error silently replacing
            // the real cause would hide what actually went wrong.
            $this->assertSame('simulated failure during start()', $e->getMessage());
        }

        $this->assertFalse(app(BackupLock::class)->isLocked(), 'lock must be released even though start()\'s own cleanup also failed');
    }

    #[Test]
    public function a_failure_before_the_swap_does_not_roll_back(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->failAt = 'download';

        $history = $applier->run($applier->start($this->manifest()));

        $this->assertSame(UpdateHistory::STATUS_FAILED, $history->status);
        $this->assertFalse($applier->rolledBack, 'nothing was swapped, so nothing to reverse');
        $this->assertStringContainsString('[download]', $history->error);
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertFalse((bool) settings('maintenance.enabled'));
    }

    #[Test]
    public function a_failure_after_the_swap_rolls_back(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->failAt = 'finalize';

        $history = $applier->run($applier->start($this->manifest()));

        $this->assertSame(UpdateHistory::STATUS_ROLLED_BACK, $history->status);
        $this->assertTrue($applier->rolledBack, 'a post-swap failure reverses files + DB');
        $this->assertFalse(app(BackupLock::class)->isLocked());
        $this->assertFalse((bool) settings('maintenance.enabled'));
    }

    #[Test]
    public function it_resumes_from_the_persisted_checkpoint(): void
    {
        $first = new FakeUpdateApplier;
        $history = $first->start($this->manifest());
        $first->advance($history->refresh()); // run only the backup step, then "crash"

        $this->assertSame(1, $history->refresh()->stepIndex());

        // A fresh instance + reloaded row continues where it left off.
        $second = new FakeUpdateApplier;
        $done = $second->run(UpdateHistory::find($history->id));

        $this->assertSame(['download', 'extract', 'swap', 'finalize', 'verify'], $second->log);
        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $done->status);
    }

    /**
     * A poll that outlasts the browser's polling interval (a slow step — e.g.
     * the pre-update backup on a large database) can overlap with the next
     * poll, both seeing the same not-yet-advanced step. Without a lock, both
     * would run doBackup() concurrently, creating two independent BackupRun
     * rows racing each other — this surfaced live as a stray "No query
     * results for model [BackupRun]" when one poll's run raced the other's.
     * advance() now takes a per-history Cache::lock() around the whole step;
     * a poll that loses the race just returns the history unchanged instead
     * of re-running (or corrupting) the step.
     */
    #[Test]
    public function a_concurrent_advance_call_on_the_same_history_is_skipped_not_re_run(): void
    {
        $applier = new FakeUpdateApplier;
        $history = $applier->start($this->manifest());

        // Simulate an overlapping poll already holding the per-history lock.
        $lock = Cache::lock('update_apply.advance.'.$history->getKey(), 300);
        $this->assertTrue($lock->get(), 'test setup: acquire the lock the real advance() would need');

        $result = $applier->advance($history->refresh());

        $this->assertSame([], $applier->log, 'the step never ran while another advance() held the lock');
        $this->assertSame(0, $result->stepIndex(), 'step index is unchanged');
        $this->assertSame(UpdateHistory::STATUS_BACKING_UP, $result->status);

        $lock->release();

        // Once the lock is free, a normal advance() proceeds as usual.
        $applier->advance($history->refresh());
        $this->assertSame(['backup'], $applier->log);
    }

    #[Test]
    public function a_slow_step_keeps_its_lock_so_a_later_poll_cannot_start_it_a_second_time(): void
    {
        // Rehearsing a 1M-product 1.0.16 -> 2.0.0 update: `finalize` (migrations) outran the
        // old 300 s lock, the next poll took the lock and started a second migrate on top
        // of the first. The lock has to survive a step far longer than five minutes.
        $applier = new class extends FakeUpdateApplier
        {
            public ?bool $rivalGotTheLock = null;

            protected function doBackup(UpdateHistory $h): void
            {
                $this->log[] = 'backup';

                // Twenty minutes into the step, another poll tries to take the same lock.
                Carbon::setTestNow(now()->addMinutes(20));
                $rival = Cache::lock('update_apply.advance.'.$h->getKey(), 10);
                $this->rivalGotTheLock = $rival->get();
                $rival->release();
            }
        };
        $history = $applier->start($this->manifest());

        try {
            $applier->advance($history);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertFalse($applier->rivalGotTheLock, 'a 20-minute step must still hold its lock');
    }

    #[Test]
    public function the_pre_update_backup_does_not_release_the_updaters_lock(): void
    {
        Storage::fake('local');
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local', 'backup.db.chunk_rows' => 100]);

        // The updater holds the lock.
        app(BackupLock::class)->acquire('update:1.1.0');

        // The pre-update backup runs WITHOUT touching the lock.
        $run = app(BackupManager::class)->start(
            BackupRun::PROFILE_UPDATE_SAFETY, BackupRun::TRIGGER_PRE_UPDATE, [], acquireLock: false
        );
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertTrue(app(BackupLock::class)->isLocked(), 'the updater still holds the lock after its backup step');

        app(BackupLock::class)->release();
    }

    #[Test]
    public function it_arms_the_recovery_console_on_start_and_disarms_on_success(): void
    {
        $arm = app(RecoveryWindowFlag::class);

        $applier = new FakeUpdateApplier;
        $history = $applier->start($this->manifest());

        $this->assertTrue($arm->isArmed(), 'the recovery window opens for the duration of the apply');
        $this->assertSame($history->getKey(), $arm->read()['history_id']);

        $applier->run($history);

        $this->assertFalse($arm->isArmed(), 'a successful update auto-disarms the console');
    }

    #[Test]
    public function a_hard_failure_leaves_the_console_armed_but_a_rollback_disarms_it(): void
    {
        $arm = app(RecoveryWindowFlag::class);

        // Pre-swap failure → status failed, no rollback → stay armed (operator territory).
        $failed = new FakeUpdateApplier;
        $failed->failAt = 'download';
        $history = $failed->run($failed->start($this->manifest()));
        $this->assertSame(UpdateHistory::STATUS_FAILED, $history->status);
        $this->assertTrue($arm->isArmed(), 'a hard failure keeps the recovery window open');

        $arm->disarm(); // reset between scenarios

        // Post-swap failure → rolled_back → known-good install → disarm.
        $rolled = new FakeUpdateApplier;
        $rolled->failAt = 'finalize';
        $history = $rolled->run($rolled->start($this->manifest()));
        $this->assertSame(UpdateHistory::STATUS_ROLLED_BACK, $history->status);
        $this->assertFalse($arm->isArmed(), 'a completed rollback closes the recovery window');
    }

    #[Test]
    public function the_preview_summarises_the_release(): void
    {
        // A clean fixture root so pre-flight can proceed.
        $root = $this->state.'/root';
        @mkdir($root.'/app', 0775, true);
        file_put_contents($root.'/.env', "APP_KEY=base64:x\n");
        config(['updates.root_path' => $root]);

        // resources/keys/release-public.pem is a REAL committed file (this
        // app's actual release trust anchor as of the v1.0.16 signing
        // rollout), so PreflightService::checkSignature() enforces it here
        // exactly like it does for a real install — sign against the test
        // fixture keypair rather than bypassing the check.
        config(['updates.signing.public_key' => ReleaseKeys::PUBLIC_KEY]);
        $manifest = $this->manifest([
            'min_php' => '8.2', 'required_extensions' => ['json'], 'min_version_to_update_from' => '0.0.0',
        ]);
        $signer = app(ReleaseSignature::class);
        $manifest['signature'] = $signer->sign($signer->payloadFor($manifest), ReleaseKeys::PRIVATE_KEY);

        $preview = app(UpdateApplier::class)->preview($manifest);

        $this->assertSame('1.1.0', $preview->toVersion);
        $this->assertSame(2, $preview->migrationCount);
        $this->assertGreaterThan(0, $preview->etaSeconds);
        $this->assertTrue($preview->canProceed());
    }

    #[Test]
    public function complete_notifies_admins_with_the_manual_trigger_when_initiated_by_an_admin(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->run($applier->start($this->manifest(), initiatedBy: 7));

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === true
            && $job->result['trigger'] === 'manual'
            && $job->result['to_version'] === '1.1.0'
        );
    }

    #[Test]
    public function complete_notifies_admins_with_the_auto_trigger_when_unattended(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->run($applier->start($this->manifest(), initiatedBy: null));

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['trigger'] === 'auto'
        );
    }

    #[Test]
    public function fail_notifies_admins_with_the_rolled_back_flag(): void
    {
        $applier = new FakeUpdateApplier;
        $applier->failAt = 'finalize';
        $applier->run($applier->start($this->manifest(), initiatedBy: 7));

        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === false
            && $job->result['rolled_back'] === true
            && $job->result['trigger'] === 'manual'
        );
    }

    #[Test]
    public function a_failed_pre_update_backup_notifies_admins_of_the_backup_failure_too(): void
    {
        // A partial fake: everything EXCEPT doBackup() is stubbed (mirrors
        // FakeUpdateApplier), so the real UpdateApplier::doBackup() runs
        // against a BackupManager double that reports a failed run —
        // exercising the actual failure-detection branch, not a mock of it.
        $failedRun = new BackupRun([
            'profile' => BackupRun::PROFILE_UPDATE_SAFETY,
            'status' => BackupRun::STATUS_FAILED,
            'error' => 'simulated backup failure',
        ]);
        $failedRun->id = 999;

        $fakeBackupManager = new class($failedRun) extends BackupManager
        {
            public function __construct(private BackupRun $failedRun) {}

            public function start(string $profile = BackupRun::PROFILE_FULL, string $trigger = BackupRun::TRIGGER_MANUAL, array $meta = [], bool $acquireLock = true): BackupRun
            {
                return $this->failedRun;
            }

            public function run(BackupRun $run): BackupRun
            {
                return $this->failedRun;
            }
        };
        app()->instance(BackupManager::class, $fakeBackupManager);

        $applier = new class extends UpdateApplier
        {
            protected function gate(array $manifest): void {}

            protected function armRecovery(UpdateHistory $history): void {}

            protected function isGitMode(): bool
            {
                return false;
            }
        };

        $history = $applier->start($this->manifest(), initiatedBy: 7);
        $applier->advance($history->refresh());

        Queue::assertPushed(NotifyAdminsOfBackupFailure::class, fn ($job) => $job->reason === 'simulated backup failure');
        Queue::assertPushed(NotifyAdminsOfUpdateResult::class, fn ($job) => $job->result['success'] === false);
    }
}
