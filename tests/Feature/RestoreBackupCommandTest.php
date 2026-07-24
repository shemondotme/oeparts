<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `oeparts:backup:restore` — CLI wrapper around App\Services\Backup\
 * RestoreManager (already proven end-to-end, including cross-server import,
 * by tests/Feature/BackupRestoreTest.php). This suite only needs to prove
 * the command-line plumbing: option parsing, the confirmation prompt,
 * --force, and both the --run and --import-manifest entry points — reusing
 * BackupRestoreTest's setup pattern.
 */
class RestoreBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-restore-cmd-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);

        // A small, dedicated fixture dir — NOT sys_get_temp_dir() itself,
        // which can contain many unrelated files from other processes and
        // makes the file-backup stage slow to enumerate.
        $this->fixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-restore-cmd-fixture-'.getmypid();
        @mkdir($this->fixture, 0775, true);
        file_put_contents($this->fixture.'/readme.txt', 'hello restore');
        config(['backup.files.root' => $this->fixture]);

        Schema::create('oe_restore_cmd_widget', function ($t) {
            $t->id();
            $t->string('name');
        });
        DB::table('oe_restore_cmd_widget')->insert(['name' => 'w1']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('oe_restore_cmd_widget');
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        @unlink($this->fixture.'/readme.txt');
        @rmdir($this->fixture);

        parent::tearDown();
    }

    private function backup(): BackupRun
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);

        return app(BackupManager::class)->run($run);
    }

    #[Test]
    public function it_requires_either_run_or_import_manifest(): void
    {
        $this->artisan('oeparts:backup:restore')
            ->assertFailed();
    }

    #[Test]
    public function it_fails_for_an_unknown_run_id(): void
    {
        $this->artisan('oeparts:backup:restore', ['--run' => 999999, '--force' => true])
            ->assertFailed();
    }

    #[Test]
    public function it_cancels_without_force_when_the_prompt_is_declined(): void
    {
        $run = $this->backup();

        DB::table('oe_restore_cmd_widget')->delete();

        $this->artisan('oeparts:backup:restore', ['--run' => $run->id, '--database' => true])
            ->expectsConfirmation('Continue?', 'no')
            ->assertSuccessful();

        // Declined — nothing restored.
        $this->assertSame(0, DB::table('oe_restore_cmd_widget')->count());
    }

    #[Test]
    public function it_restores_the_database_with_force(): void
    {
        $run = $this->backup();

        DB::table('oe_restore_cmd_widget')->delete();
        $this->assertSame(0, DB::table('oe_restore_cmd_widget')->count());

        $this->artisan('oeparts:backup:restore', [
            '--run' => $run->id,
            '--database' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('oe_restore_cmd_widget')->count());
    }

    #[Test]
    public function it_restores_cross_server_via_import_manifest(): void
    {
        $run = $this->backup();
        $manifestPath = $run->manifest_path;

        DB::table('oe_restore_cmd_widget')->delete();

        // Simulate a fresh server: no backup_runs/backup_parts rows, only the
        // files + unencrypted manifest.json TOC on disk survive.
        BackupChunk::query()->delete();
        BackupRun::query()->delete();

        $this->artisan('oeparts:backup:restore', [
            '--import-manifest' => $manifestPath,
            '--disk' => 'local',
            '--database' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('oe_restore_cmd_widget')->count());
        $this->assertSame(1, BackupRun::count(), 'the imported manifest recreated exactly one run');
    }
}
