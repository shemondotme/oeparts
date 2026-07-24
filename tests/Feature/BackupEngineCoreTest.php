<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use App\Services\Backup\BackupManifest;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Backup\Exceptions\BackupLockException;
use App\Services\Backup\StageStepResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Backup Engine core (Module 21, Chunk 2.1).
 *
 * Exercises the chunked, resumable FSM (BackupManager), the shared lock, the
 * manifest, and the janitor — against fake stages, so no real DB/file backup
 * work is needed at this layer. Asserts observable state (rows, files, lock,
 * manifest contents), never merely "no error" (testing strategy in the workflow).
 */
class BackupEngineCoreTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Isolate the shared lock file from the real storage/app/updates dir.
        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-backup-test-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local']);

        // Every test declares its own pipeline explicitly.
        config(['backup.stages' => ['update_safety' => [], 'full' => []]]);
    }

    protected function tearDown(): void
    {
        // Clear the lock + temp state dir between tests.
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);

        parent::tearDown();
    }

    private function manager(): BackupManager
    {
        return app(BackupManager::class);
    }

    #[Test]
    public function start_creates_a_running_run_and_acquires_the_shared_lock(): void
    {
        $run = $this->manager()->start(BackupRun::PROFILE_FULL, BackupRun::TRIGGER_MANUAL);

        $this->assertSame(BackupRun::STATUS_RUNNING, $run->status);
        $this->assertSame(BackupRun::PROFILE_FULL, $run->profile);
        $this->assertNotNull($run->started_at);
        $this->assertSame(PHP_VERSION, $run->php_version);
        $this->assertTrue(app(BackupLock::class)->isLocked());
        $this->assertSame($run->lockOwner(), app(BackupLock::class)->owner()['owner'] ?? null);
    }

    #[Test]
    public function an_invalid_profile_is_rejected(): void
    {
        $this->expectException(BackupException::class);

        $this->manager()->start('nonsense');
    }

    #[Test]
    public function a_second_run_is_blocked_by_the_lock_and_leaves_no_orphan_row(): void
    {
        $this->manager()->start(BackupRun::PROFILE_FULL);

        try {
            $this->manager()->start(BackupRun::PROFILE_FULL);
            $this->fail('Expected the lock to block a concurrent run.');
        } catch (BackupLockException $e) {
            // expected
        }

        // The blocked run must not persist a dangling row.
        $this->assertSame(1, BackupRun::count());
    }

    #[Test]
    public function a_run_with_no_stages_finalises_successfully_with_a_manifest(): void
    {
        $run = $this->manager()->start(BackupRun::PROFILE_FULL);
        $run = $this->manager()->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(0, $run->part_count);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->checksum);
        $this->assertNotNull($run->manifest_path);
        Storage::disk('local')->assertExists($run->manifest_path);

        // Lock is released on success.
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function the_engine_drives_stages_one_chunk_at_a_time_and_records_parts(): void
    {
        config(['backup.stages.full' => [new FakeDbStage(chunks: 3)]]);

        $run = $this->manager()->start(BackupRun::PROFILE_FULL);

        // Each advance() performs exactly one chunk — assert incremental progress.
        $this->manager()->advance($run->refresh());
        $this->assertSame(1, $run->refresh()->parts()->count(), 'one part after one poll');

        $run = $this->manager()->run($run->refresh());

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(3, $run->part_count);
        $this->assertSame(3, BackupChunk::where('backup_run_id', $run->id)->count());
        $this->assertSame((int) $run->parts->sum('bytes'), (int) $run->total_bytes);
        $this->assertFalse(app(BackupLock::class)->isLocked());

        // Parts are stored on disk and the manifest indexes them.
        $manifest = app(BackupManifest::class)->read($run);
        $this->assertNotNull($manifest);
        $this->assertCount(3, $manifest['parts']);
        $this->assertSame('table_0', $manifest['parts'][0]['name']);
        Storage::disk('local')->assertExists($manifest['parts'][0]['path']);
    }

    #[Test]
    public function a_partly_advanced_run_resumes_from_its_persisted_checkpoint(): void
    {
        config(['backup.stages.full' => [new FakeDbStage(chunks: 3)]]);

        $run = $this->manager()->start(BackupRun::PROFILE_FULL);
        $this->manager()->advance($run->refresh()); // one chunk, then "crash"

        $checkpoint = $run->refresh()->checkpoint();
        $this->assertSame(0, $checkpoint['stage_index']);
        $this->assertSame(1, $checkpoint['stage_state']['i']);

        // A completely fresh manager + fresh model reload — nothing in memory.
        $freshRun = BackupRun::find($run->id);
        $finished = app(BackupManager::class)->run($freshRun);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $finished->status);
        $this->assertSame(3, $finished->part_count);
    }

    #[Test]
    public function a_stage_exception_fails_the_run_and_releases_the_lock(): void
    {
        config(['backup.stages.full' => [new FakeThrowingStage()]]);

        $run = $this->manager()->start(BackupRun::PROFILE_FULL);
        $run = $this->manager()->run($run);

        $this->assertSame(BackupRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('disk exploded', (string) $run->error);
        $this->assertStringContainsString('[files]', (string) $run->error);
        $this->assertNotNull($run->finished_at);
        $this->assertFalse(app(BackupLock::class)->isLocked());
    }

    #[Test]
    public function the_janitor_purges_a_failed_partial_and_marks_it_cleaned(): void
    {
        // A failed run with an on-disk part left behind.
        Storage::disk('local')->put('backups/999/db-0.gz', 'leftover');

        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status'  => BackupRun::STATUS_FAILED,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk'    => 'local',
            'started_at' => now()->subMinutes(5),
        ]);
        $run->parts()->create([
            'type' => 'db', 'sequence' => 0, 'disk' => 'local',
            'path' => 'backups/999/db-0.gz', 'bytes' => 8,
        ]);

        $cleaned = app(BackupJanitor::class)->cleanupPartials();

        $this->assertSame(1, $cleaned);
        Storage::disk('local')->assertMissing('backups/999/db-0.gz');
        $this->assertNotNull($run->refresh()->meta['cleaned_at'] ?? null);
    }

    #[Test]
    public function the_janitor_releases_a_stale_lock(): void
    {
        // Hand-write a lock file with an old timestamp (a crashed run's lock).
        $lock = app(BackupLock::class);
        file_put_contents($lock->path(), json_encode([
            'owner'       => 'backup:dead',
            'acquired_at' => now()->subHours(2)->toIso8601String(),
        ]));
        $this->assertTrue($lock->isLocked());

        app(BackupJanitor::class)->cleanupPartials();

        $this->assertFalse($lock->isLocked(), 'stale lock should be released');
    }

    #[Test]
    public function the_janitor_leaves_a_fresh_lock_alone(): void
    {
        $lock = app(BackupLock::class);
        $lock->acquire('backup:live'); // just now — not stale

        app(BackupJanitor::class)->cleanupPartials();

        $this->assertTrue($lock->isLocked(), 'a live lock must not be reaped');
    }
}

/* ---- Fake stages (test doubles for the 2.2/2.3 concrete stages) ---------- */

class FakeDbStage implements BackupStage
{
    public function __construct(private int $chunks = 3) {}

    public function key(): string
    {
        return BackupChunk::TYPE_DB;
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        $i    = (int) ($state['i'] ?? 0);
        $data = "chunk-{$i}-data";
        $path = "backups/{$run->id}/db-{$i}.gz";

        Storage::disk($run->disk)->put($path, $data);

        $part = [
            'type'   => BackupChunk::TYPE_DB,
            'name'   => "table_{$i}",
            'path'   => $path,
            'bytes'  => strlen($data),
            'rows'   => 10,
            'sha256' => hash('sha256', $data),
        ];

        $next = $i + 1;

        return $next >= $this->chunks
            ? StageStepResult::complete($part, 'db stage complete')
            : StageStepResult::progress(['i' => $next], $part, "db chunk {$next}");
    }
}

class FakeThrowingStage implements BackupStage
{
    public function key(): string
    {
        return BackupChunk::TYPE_FILES;
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        throw new \RuntimeException('disk exploded');
    }
}
