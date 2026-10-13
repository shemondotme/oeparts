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
            'app.txt' => 'hello world',
            'sub/nested.txt' => 'nested content',
            'empty.txt' => '',
            'node_modules/x.txt' => 'should be excluded',
            'storage/framework/phpstan/cache/x.php' => 'should be excluded',
            'dist/export/vendor/pkg/file.txt' => 'should be excluded',
            // Real layout of the default 'local' disk (root = storage/app/private): every
            // backup run's volumes live here and must never be re-archived into the next.
            'storage/app/private/backups/7/files/vol-0.oevol' => 'an earlier backup volume',
            'storage/app/restore/run-3/app/Model.php' => 'restore staging copy',
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
            'status' => BackupRun::STATUS_RUNNING,
            'trigger' => BackupRun::TRIGGER_MANUAL,
            'disk' => 'local',
            'meta' => $meta,
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
                $p = $result->part;
                $type = $p['type'] ?? BackupChunk::TYPE_FILES;
                $run->parts()->create([
                    'type' => $type,
                    'sequence' => $run->parts()->where('type', $type)->count(),
                    'name' => $p['name'] ?? null,
                    'disk' => $p['disk'] ?? $run->disk,
                    'path' => $p['path'] ?? '',
                    'sha256' => $p['sha256'] ?? null,
                    'bytes' => $p['bytes'] ?? 0,
                    'rows' => $p['rows'] ?? null,
                    'meta' => $p['meta'] ?? null,
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
        $out = '';
        foreach ($entry['segments'] as [$offset, $clen, $rawLen]) {
            $out .= gzdecode(substr($volBytes, $offset, $clen));
        }

        return $out;
    }

    #[Test]
    public function it_archives_files_into_a_volume_with_a_manifest(): void
    {
        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage, $run);

        $volumes = $run->parts()->where('meta->kind', 'volume')->count();
        $manifest = $this->manifest($run);

        $this->assertGreaterThanOrEqual(1, $volumes);
        $this->assertSame(3, $manifest['counts']['archived'], 'app + nested + empty (node_modules, phpstan cache, dist excluded)');
        $this->assertSame(3, $manifest['counts']['file_count']);

        $paths = array_column($manifest['files'], 'path');
        $this->assertContains('app.txt', $paths);
        $this->assertContains('sub/nested.txt', $paths);
        $this->assertNotContains('node_modules/x.txt', $paths, 'excluded dir must not appear');
        // Regression (2026-09-22): both grew large enough at 100k-scale to bloat
        // the file-scan checkpoint JSON past MySQL's JSON column limits, crashing
        // the whole backup run — see config/backup.php's files.exclude comments.
        $this->assertNotContains('storage/framework/phpstan/cache/x.php', $paths, 'PHPStan result cache must not appear');
        $this->assertNotContains('dist/export/vendor/pkg/file.txt', $paths, 'ReleaseBuilder build output must not appear');
        // Regression (2026-10-11): measured on a real run — 376 files of earlier backups
        // were archived inside a later backup, so backups grew with every run.
        $this->assertNotContains('storage/app/private/backups/7/files/vol-0.oevol', $paths, 'earlier backups must not be re-archived');
        $this->assertNotContains('storage/app/restore/run-3/app/Model.php', $paths, 'restore staging must not be archived');
    }

    #[Test]
    public function a_custom_local_staging_disk_inside_the_project_tree_is_excluded_too(): void
    {
        // An operator can point the staging disk at any local directory, including one
        // under the project root; the static config list cannot know about it.
        $this->writeFixture(['var/staging/backups/8/files/vol-0.oevol' => 'earlier backup on a custom disk']);
        config([
            'filesystems.disks.stage_inside_root' => ['driver' => 'local', 'root' => $this->fixture.'/var/staging'],
            'backup.staging_disk' => 'stage_inside_root',
        ]);

        $run = $this->newRun();
        $stage = new FileBackupStage;
        $state = [];
        $guard = 0;
        do {
            $result = $stage->step($run, $state);
            if ($result->part !== null) {
                $p = $result->part;
                $type = $p['type'] ?? BackupChunk::TYPE_FILES;
                $run->parts()->create([
                    'type' => $type,
                    'sequence' => $run->parts()->where('type', $type)->count(),
                    'name' => $p['name'] ?? null,
                    'disk' => $p['disk'] ?? $run->disk,
                    'path' => $p['path'] ?? '',
                    'sha256' => $p['sha256'] ?? null,
                    'bytes' => $p['bytes'] ?? 0,
                    'rows' => $p['rows'] ?? null,
                    'meta' => $p['meta'] ?? null,
                ]);
            }
            $state = $result->state;
        } while (! $result->done && ++$guard < 100000);

        $part = $run->parts()->where('name', 'files-manifest')->firstOrFail();
        $manifest = json_decode(gzdecode(Storage::disk($part->disk)->get($part->path)), true);
        $paths = array_column($manifest['files'], 'path');

        $this->assertContains('app.txt', $paths);
        foreach ($paths as $path) {
            $this->assertStringStartsNotWith('var/staging/backups', $path, 'the staging disk backups dir must be excluded');
        }
    }

    #[Test]
    public function an_archived_file_round_trips_byte_for_byte(): void
    {
        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage, $run);

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
        $this->driveAndPersist(new FileBackupStage, $run);

        $this->assertGreaterThanOrEqual(2, $run->parts()->where('meta->kind', 'volume')->count());
        // Content still reconstructs correctly across the split.
        $entry = collect($this->manifest($run)['files'])->firstWhere('path', 'sub/nested.txt');
        $this->assertSame('nested content', $this->extract($run, $entry));
    }

    #[Test]
    public function an_empty_file_is_recorded_with_no_segments(): void
    {
        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage, $run);

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
        $this->driveAndPersist(new FileBackupStage, $first);
        $first->update(['status' => BackupRun::STATUS_SUCCESS]);

        // Change one file, add one, delete one.
        file_put_contents($this->fixture.'/app.txt', 'CHANGED');
        touch($this->fixture.'/app.txt', time() + 10);        // force a different mtime
        file_put_contents($this->fixture.'/added.txt', 'new file');
        @unlink($this->fixture.'/empty.txt');

        $second = $this->newRun(['incremental' => true]);
        $this->driveAndPersist(new FileBackupStage, $second);

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

    /**
     * Regression (2026-10-13, found while auditing what a backup carries): only the
     * exact path ".env" was excluded, so ".env.production", ".env.bak" and friends were
     * archived, and so was bootstrap/cache — whose config.php bakes this server's DB
     * credentials, APP_KEY, mail password and APP_URL into a file a restore onto another
     * server would then load instead of that server's own settings.
     */
    #[Test]
    public function other_env_files_the_framework_caches_and_per_instance_state_are_not_archived(): void
    {
        $this->writeFixture([
            '.env' => 'APP_KEY=secret',
            '.env.production' => 'APP_KEY=secret',
            '.env.bak' => 'APP_KEY=secret',
            '.ENV.local' => 'APP_KEY=secret',
            'docs/.env.sample' => 'APP_KEY=',
            'environment.md' => 'about the environment',
            'bootstrap/cache/config.php' => '<?php return ["app" => ["key" => "secret"]];',
            'bootstrap/cache/.gitignore' => '*',
            'storage/app/install/state.json' => '{}',
            'storage/app/import/site.oeparts' => 'a package waiting to be imported',
            'storage/app/migration/exports/site.oeparts' => 'an exported package',
            'storage/app/instance/instance.json' => '{}',
            'storage/app/public/product.jpg' => 'user upload',
        ]);

        $run = $this->newRun();
        $this->driveAndPersist(new FileBackupStage, $run);
        $paths = array_column($this->manifest($run)['files'], 'path');

        foreach ([
            '.env', '.env.production', '.env.bak', '.ENV.local',
            'bootstrap/cache/config.php', 'bootstrap/cache/.gitignore',
            'storage/app/install/state.json', 'storage/app/import/site.oeparts',
            'storage/app/migration/exports/site.oeparts', 'storage/app/instance/instance.json',
        ] as $excluded) {
            $this->assertNotContains($excluded, $paths, "{$excluded} must not be archived");
        }

        // Look-alikes and real content stay: the glob only matches a root-level .env.* name.
        $this->assertContains('docs/.env.sample', $paths, 'a nested .env.sample is not a secret file');
        $this->assertContains('environment.md', $paths);
        $this->assertContains('storage/app/public/product.jpg', $paths);
    }

    #[Test]
    public function a_file_that_becomes_excluded_is_reported_as_deleted_by_the_next_incremental_backup(): void
    {
        $this->writeFixture(['bootstrap/cache/services.php' => '<?php return [];']);

        // The baseline predates the exclusion: pretend it archived the cache file.
        $first = $this->newRun();
        config(['backup.files.exclude' => ['node_modules', 'storage/framework/phpstan', 'dist']]);
        $this->driveAndPersist(new FileBackupStage, $first);
        $first->update(['status' => BackupRun::STATUS_SUCCESS]);
        $this->assertContains('bootstrap/cache/services.php', array_column($this->manifest($first)['files'], 'path'));

        // Now the exclusion is in place (as shipped).
        config(['backup.files.exclude' => ['node_modules', 'storage/framework/phpstan', 'dist', 'bootstrap/cache']]);
        $second = $this->newRun(['incremental' => true]);
        $this->driveAndPersist(new FileBackupStage, $second);

        // A deletion is recorded as a {path, deleted: true} entry, never as an archived file.
        $manifest = $this->manifest($second);
        $entries = array_values(array_filter($manifest['files'], fn ($e) => $e['path'] === 'bootstrap/cache/services.php'));

        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]['deleted'] ?? false, 'the newly excluded file is reported as deleted, not archived again');
        $this->assertGreaterThanOrEqual(1, $manifest['counts']['deleted']);
    }
}
