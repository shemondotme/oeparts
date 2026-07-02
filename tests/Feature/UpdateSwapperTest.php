<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\BackupManager;
use App\Services\Updates\UpdateSwapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update file swap + pre-update safety backup (Module 21, Chunk 3.3). The swapper
 * runs against a FIXTURE root (never the real project) so tests can safely mutate
 * an "install".
 */
class UpdateSwapperTest extends TestCase
{
    use RefreshDatabase;

    private string $base;
    private string $root;
    private string $staging;
    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base    = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-swap-'.getmypid();
        $this->root    = $this->base.'/root';
        $this->staging = $this->base.'/staging';
        $this->state   = $this->base.'/state';

        // Live "install".
        $this->writeFile($this->root.'/app/OldClass.php', '<?php // old');
        $this->writeFile($this->root.'/config/app.php', 'old-config');
        $this->writeFile($this->root.'/composer.json', 'old-composer');
        $this->writeFile($this->root.'/version.json', '{"version":"1.0.1"}');
        // PRESERVE paths — must never be touched.
        $this->writeFile($this->root.'/.env', 'APP_SECRET=keepme');
        $this->writeFile($this->root.'/storage/app/user.dat', 'user-data');

        // Extracted new release (adds public/build, which the install lacks).
        $this->writeFile($this->staging.'/app/NewClass.php', '<?php // new');
        $this->writeFile($this->staging.'/config/app.php', 'new-config');
        $this->writeFile($this->staging.'/composer.json', 'new-composer');
        $this->writeFile($this->staging.'/version.json', '{"version":"1.1.0"}');
        $this->writeFile($this->staging.'/public/build/manifest.json', 'new-build');

        config([
            'updates.root_path'  => $this->root,
            'updates.state_path' => $this->state,
            'updates.core_paths' => ['app', 'config', 'composer.json', 'version.json', 'public/build'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function writeFile(string $path, string $contents): void
    {
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
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

    #[Test]
    public function it_swaps_core_paths_and_backs_up_the_originals(): void
    {
        $map = app(UpdateSwapper::class)->swap($this->staging, '1.1.0');

        // New code is live.
        $this->assertFileExists($this->root.'/app/NewClass.php');
        $this->assertFileDoesNotExist($this->root.'/app/OldClass.php');
        $this->assertSame('new-config', file_get_contents($this->root.'/config/app.php'));
        $this->assertStringContainsString('1.1.0', file_get_contents($this->root.'/version.json'));
        $this->assertSame('new-build', file_get_contents($this->root.'/public/build/manifest.json'));

        // Originals are preserved in the swap-backup for rollback.
        $this->assertStringContainsString('1.0.1', file_get_contents($map['backup_dir'].'/version.json'));
        $this->assertFileExists($map['backup_dir'].'/app/OldClass.php');

        // PRESERVE paths untouched.
        $this->assertSame('APP_SECRET=keepme', file_get_contents($this->root.'/.env'));
        $this->assertSame('user-data', file_get_contents($this->root.'/storage/app/user.dat'));

        // Recovery contract.
        $this->assertTrue($map['completed']);
        $this->assertCount(5, $map['swapped']);
        $this->assertFileExists(app(UpdateSwapper::class)->stateFile());
    }

    #[Test]
    public function rollback_restores_the_originals_and_removes_new_paths(): void
    {
        $swapper = app(UpdateSwapper::class);
        $swapper->swap($this->staging, '1.1.0');

        $swapper->rollback();

        $this->assertFileExists($this->root.'/app/OldClass.php');
        $this->assertFileDoesNotExist($this->root.'/app/NewClass.php');
        $this->assertSame('old-config', file_get_contents($this->root.'/config/app.php'));
        $this->assertStringContainsString('1.0.1', file_get_contents($this->root.'/version.json'));

        // public/build had no original → rollback removes it entirely.
        $this->assertFileDoesNotExist($this->root.'/public/build/manifest.json');

        // The recovery state file is cleared once rolled back.
        $this->assertFileDoesNotExist($swapper->stateFile());
    }

    #[Test]
    public function the_recovery_state_records_the_swap_map(): void
    {
        app(UpdateSwapper::class)->swap($this->staging, '1.1.0');

        $state = app(UpdateSwapper::class)->readState();

        $this->assertSame('1.1.0', $state['version']);
        $this->assertTrue($state['completed']);
        $this->assertSame($this->root, $state['root']);
        $this->assertContains('app', array_column($state['swapped'], 'path'));
    }

    #[Test]
    public function the_pre_update_safety_backup_captures_the_database_only(): void
    {
        Storage::fake('local');
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local', 'backup.db.chunk_rows' => 100]);

        $run = app(BackupManager::class)->start(BackupRun::PROFILE_UPDATE_SAFETY, BackupRun::TRIGGER_PRE_UPDATE);
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(BackupRun::TRIGGER_PRE_UPDATE, $run->trigger);
        $this->assertGreaterThan(0, $run->parts()->where('type', 'db')->count());
        $this->assertSame(0, $run->parts()->where('type', 'files')->count(), 'update_safety skips files');
    }
}
