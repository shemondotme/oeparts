<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Stages\FileBackupStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FileBackupStage (Module 21, Chunk 2.3) — the second concrete BackupStage.
 *
 * Backs up a fixture file tree (not the real project) into gzip volumes with a
 * hash manifest. Asserts observable output: real volume/manifest parts, that an
 * archived file can be reconstructed byte-for-byte from its recorded segments,
 * exclusions, volume rollover, and incremental changed/unchanged/deleted diffing.
 */
class FileBackupStageTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-file-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local']);

        // A small fixture tree to back up.
        $this->fixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-file-fixture-'.getmypid();
        $this->writeFixture([
            'app.txt'         => 'hello world',
            'sub/nested.txt'  => 'nested content',
            'empty.txt'       => '',
            'node_modules/x.txt' => 'should be excluded',
        ]);
        config(['backup.files.root' => $this->fixture]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->fixture);
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);

        parent::tearDown();
    }

    private function writeFixture(array $files): void
    {
        foreach ($files as $rel => $contents) {
            $abs = $this->fixture.'/'.$rel;
            @mkdir(dirname($abs), 0775, true);
            file_put_contents($abs, $contents);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function newRun(array $meta = []): BackupRun
    {
        return BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL,
            'status'  => BackupRun::STATUS_RUNNING,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk'    => 'local',
            'meta'    => $meta,
        ]);
    }

    /** Drive the stage to completion, persisting each part like the engine does. */
    private function driveAndPersist(FileBackupStage $stage, BackupRun $run): void
    {
        $state = [];
        $guard = 0;

        do {
            $result = $stage->step($run, $state);
            if ($result->part !== null) {
                $p    = $result->part;
                $type = $p['type'] ?? BackupChunk::TYPE_FILES;
                $run->parts()->create([
                    'type'     => $type,
                    'sequence' => $run->parts()->where('type', $type)->count(),
                    'name'     => $p['name'] ?? null,
                    'disk'     => $p['disk'] ?? $run->disk,
                    'path'     => $p['path'] ?? '',
                    'sha256'   => $p['sha256'] ?? null,
                    'bytes'    => $p['bytes'] ?? 0,
                    'rows'     => $p['rows'] ?? null,
                    'meta'     => $p['meta'] ?? null,
                ]);
            }
            $state = $result->state;
        } while (! $result->done && ++$guard < 100000);
    }

    private function manifest(BackupRun $run): array
    {
        $part = $run->parts()->where('name', 'files-manifest')->firstOrFail();

        return json_decode(gzdecode(Storage::disk('local')->get($part->path)), true);
    }

    /** Reconstruct an archived file from its recorded volume segments. */
    private function extract(BackupRun $run, array $entry): string
    {
        $volBytes = Storage::disk('local')->get('backups/'.$run->id.'/files/vol-'.$entry['vol'].'.oevol');
        $out      = '';
        foreach ($entry['segments'] as [$offset, $clen, $rawLen]) {
            $out .= gzdecode(substr($volBytes, $offset, $clen));
        }

        return $out;
    }

    #[Test]
    public function it_archives_files_into_a_volume_with_a_manifest(): void
    {
        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage(), $run);

        $volumes  = $run->parts()->where('meta->kind', 'volume')->count();
        $manifest = $this->manifest($run);

        $this->assertGreaterThanOrEqual(1, $volumes);
        $this->assertSame(3, $manifest['counts']['archived'], 'app + nested + empty (node_modules excluded)');
        $this->assertSame(3, $manifest['counts']['file_count']);

        $paths = array_column($manifest['files'], 'path');
        $this->assertContains('app.txt', $paths);
        $this->assertContains('sub/nested.txt', $paths);
        $this->assertNotContains('node_modules/x.txt', $paths, 'excluded dir must not appear');
    }

    #[Test]
    public function an_archived_file_round_trips_byte_for_byte(): void
    {
        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage(), $run);

        $entry = collect($this->manifest($run)['files'])->firstWhere('path', 'app.txt');

        $this->assertSame('hello world', $this->extract($run, $entry));
        $this->assertSame(hash('sha256', 'hello world'), $entry['sha256']);
    }

    #[Test]
    public function large_content_splits_into_multiple_volumes(): void
    {
        // Tiny cap forces a new volume per non-empty file.
        config(['backup.volume_bytes' => 1]);

        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage(), $run);

        $this->assertGreaterThanOrEqual(2, $run->parts()->where('meta->kind', 'volume')->count());
        // Content still reconstructs correctly across the split.
        $entry = collect($this->manifest($run)['files'])->firstWhere('path', 'sub/nested.txt');
        $this->assertSame('nested content', $this->extract($run, $entry));
    }

    #[Test]
    public function an_empty_file_is_recorded_with_no_segments(): void
    {
        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage(), $run);

        $entry = collect($this->manifest($run)['files'])->firstWhere('path', 'empty.txt');

        $this->assertSame(0, $entry['size']);
        $this->assertSame([], $entry['segments']);
        $this->assertSame('', $this->extract($run, $entry));
    }

    #[Test]
    public function an_incremental_backup_diffs_against_the_previous_full(): void
    {
        // Baseline full backup.
        $first = $this->newRun();
        $this->driveAndPersist(new FileBackupStage(), $first);
        $first->update(['status' => BackupRun::STATUS_SUCCESS]);

        // Change one file, add one, delete one.
        file_put_contents($this->fixture.'/app.txt', 'CHANGED');
        touch($this->fixture.'/app.txt', time() + 10);        // force a different mtime
        file_put_contents($this->fixture.'/added.txt', 'new file');
        @unlink($this->fixture.'/empty.txt');

        $second = $this->newRun(['incremental' => true]);
        $this->driveAndPersist(new FileBackupStage(), $second);

        $counts = $this->manifest($second)['counts'];

        $this->assertSame($first->id, $this->manifest($second)['baseline_run_id']);
        $this->assertSame(2, $counts['archived'], 'changed app.txt + new added.txt');
        $this->assertSame(1, $counts['unchanged'], 'sub/nested.txt untouched');
        $this->assertSame(1, $counts['deleted'], 'empty.txt removed');
    }

    #[Test]
    public function the_manager_runs_the_full_pipeline_including_files(): void
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->parts()->where('name', 'files-manifest')->count());
        $this->assertGreaterThanOrEqual(1, $run->parts()->where('meta->kind', 'volume')->count());
        // Both stages contributed.
        $this->assertGreaterThan(0, $run->parts()->where('type', BackupChunk::TYPE_DB)->count());
        $this->assertGreaterThan(0, $run->parts()->where('type', BackupChunk::TYPE_FILES)->count());
    }

    #[Test]
    public function the_update_safety_profile_skips_files(): void
    {
        // Only the DB stage is registered for update_safety — no file volumes.
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_UPDATE_SAFETY);
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(0, $run->parts()->where('type', BackupChunk::TYPE_FILES)->count());
    }
}
