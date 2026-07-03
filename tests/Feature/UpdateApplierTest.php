<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\UpdateHistory;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\UpdateApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
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
            'version'         => '1.1.0',
            'channel'         => 'stable',
            'size_bytes'      => 1024,
            'migration_count' => 2,
            'download_url'    => 'https://x/oeparts.zip',
            'sha256'          => str_repeat('a', 64),
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
        $arm = app(\App\Services\Updates\RecoveryArm::class);

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
        $arm = app(\App\Services\Updates\RecoveryArm::class);

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

        $preview = app(UpdateApplier::class)->preview($this->manifest([
            'min_php' => '8.2', 'required_extensions' => ['json'], 'min_version_to_update_from' => '0.0.0',
        ]));

        $this->assertSame('1.1.0', $preview->toVersion);
        $this->assertSame(2, $preview->migrationCount);
        $this->assertGreaterThan(0, $preview->etaSeconds);
        $this->assertTrue($preview->canProceed());
    }
}

/**
 * Test double: records step order, optionally fails at a named step, and stubs
 * the swap/DB rollback — no real download / swap / restore.
 */
class FakeUpdateApplier extends UpdateApplier
{
    public array $log = [];
    public ?string $failAt = null;
    public bool $rolledBack = false;

    protected function gate(array $manifest): void {}

    protected function doBackup(UpdateHistory $h): void { $this->tick('backup'); }
    protected function doDownload(UpdateHistory $h): void { $this->tick('download'); }
    protected function doExtract(UpdateHistory $h): void { $this->tick('extract'); }
    protected function doSwap(UpdateHistory $h): void { $this->tick('swap'); }
    protected function doFinalize(UpdateHistory $h): void { $this->tick('finalize'); }
    protected function doVerify(UpdateHistory $h): void { $this->tick('verify'); }

    protected function rollback(UpdateHistory $h): void { $this->rolledBack = true; }

    private function tick(string $name): void
    {
        $this->log[] = $name;
        if ($this->failAt === $name) {
            throw new UpdateException('fail@'.$name);
        }
    }
}
