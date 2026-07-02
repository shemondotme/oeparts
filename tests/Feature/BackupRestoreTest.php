<?php

namespace Tests\Feature;

use App\Models\BackupPart;
use App\Models\BackupRun;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Exceptions\RestoreException;
use App\Services\Backup\RestoreManager;
use App\Services\Backup\RestoreOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Restore engine (Module 21, Chunk 2.5) — reassemble + verify + decrypt.
 *
 * Backs up with the real (encrypted) pipeline, then restores: single-table DB
 * round-trip, files round-trip with per-file verification, integrity-failure
 * detection, cross-server import-then-restore, and app_version guarding.
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;
    private string $fixture;
    private string $restoreTarget;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-restore-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);
        config(['backup.db.chunk_rows' => 2]);

        $this->fixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-restore-fixture-'.getmypid();
        @mkdir($this->fixture.'/sub', 0775, true);
        file_put_contents($this->fixture.'/readme.txt', 'hello restore');
        file_put_contents($this->fixture.'/sub/data.bin', str_repeat('X', 5000));
        config(['backup.files.root' => $this->fixture]);

        $this->restoreTarget = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-restore-out-'.getmypid();

        Schema::create('oe_restore_widget', function ($t) {
            $t->id();
            $t->string('name');
        });
        DB::table('oe_restore_widget')->insert([
            ['name' => 'w1'], ['name' => 'w2'], ['name' => 'w3'], ['name' => 'w4'], ['name' => 'w5'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('oe_restore_widget');
        $this->rrmdir($this->fixture);
        $this->rrmdir($this->restoreTarget);
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
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function backup(): BackupRun
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);

        return app(BackupManager::class)->run($run);
    }

    #[Test]
    public function it_restores_a_single_table_round_trip(): void
    {
        $run = $this->backup();

        DB::table('oe_restore_widget')->delete();
        $this->assertSame(0, DB::table('oe_restore_widget')->count());

        $report = app(RestoreManager::class)->restore(
            $run, RestoreOptions::databaseOnly('oe_restore_widget')
        );

        $this->assertSame(5, DB::table('oe_restore_widget')->count());
        $this->assertContains('oe_restore_widget', $report->tablesRestored);
        $this->assertTrue($report->ok());
    }

    #[Test]
    public function database_only_restore_writes_no_files(): void
    {
        $run    = $this->backup();
        $report = app(RestoreManager::class)->restore($run, RestoreOptions::databaseOnly());

        $this->assertSame(0, $report->filesRestored);
        $this->assertFalse(is_dir($this->restoreTarget), 'no files should be written');
    }

    #[Test]
    public function it_restores_files_and_verifies_each_one(): void
    {
        $run = $this->backup();

        $report = app(RestoreManager::class)->restore(
            $run, RestoreOptions::filesOnly($this->restoreTarget)
        );

        $this->assertSame('hello restore', file_get_contents($this->restoreTarget.'/readme.txt'));
        $this->assertSame(str_repeat('X', 5000), file_get_contents($this->restoreTarget.'/sub/data.bin'));
        $this->assertSame($report->filesRestored, $report->filesVerified);
        $this->assertGreaterThanOrEqual(2, $report->filesRestored);
        $this->assertSame([], $report->tablesRestored, 'files-only must not touch the DB');
        $this->assertTrue($report->ok());
    }

    #[Test]
    public function a_corrupted_part_fails_the_integrity_check(): void
    {
        $run = $this->backup();

        $part = $run->parts()->where('type', BackupPart::TYPE_DB)->firstOrFail();
        Storage::disk($part->disk)->put($part->path, 'corrupted-bytes');

        $this->expectException(RestoreException::class);
        app(RestoreManager::class)->restore($run, RestoreOptions::databaseOnly());
    }

    #[Test]
    public function it_restores_cross_server_from_an_imported_manifest(): void
    {
        $run          = $this->backup();
        $manifestPath = $run->manifest_path;

        // Simulate a fresh server: drop the DB rows but keep the backup files on disk.
        BackupPart::query()->delete();
        BackupRun::query()->delete();
        $this->assertSame(0, BackupRun::count());

        $imported = app(RestoreManager::class)->importManifest('local', $manifestPath);
        $this->assertGreaterThan(0, $imported->parts()->count());
        $this->assertNotNull($imported->app_version);

        $report = app(RestoreManager::class)->restore(
            $imported, RestoreOptions::filesOnly($this->restoreTarget)
        );

        $this->assertSame('hello restore', file_get_contents($this->restoreTarget.'/readme.txt'));
        $this->assertSame($report->filesRestored, $report->filesVerified);
    }

    #[Test]
    public function restoring_a_newer_backup_onto_older_code_is_guarded(): void
    {
        $run = $this->backup();
        $run->update(['app_version' => '999.0.0']); // pretend it came from a newer release

        // Non-strict: a warning, no throw.
        $report = app(RestoreManager::class)->restore($run, new RestoreOptions(
            database: false, files: false
        ));
        $this->assertNotEmpty($report->warnings);

        // Strict: refuse.
        $this->expectException(RestoreException::class);
        app(RestoreManager::class)->restore($run, new RestoreOptions(
            database: false, files: false, strictVersion: true
        ));
    }
}
