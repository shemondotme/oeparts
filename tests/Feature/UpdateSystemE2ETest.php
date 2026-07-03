<?php

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateDownloader;
use App\Services\Updates\UpdateExtractor;
use App\Services\Updates\UpdateSwapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update System end-to-end (Module 21, Chunk 5.3). The capstone integration proof:
 * a real release zip is built, served through a faked HTTP endpoint (the "local mock
 * server"), and driven through the REAL download → extract → swap → rollback pipeline
 * against a fixture install; then the whole apply FSM is run with a real pre-update
 * backup and a forced post-swap failure to prove the failure/rollback matrix reverses
 * BOTH files and the database with the real services (no stubbed swap/restore).
 */
class UpdateSystemE2ETest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private string $root;

    private string $staging;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base    = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-e2e-'.getmypid();
        $this->root    = $this->base.'/root';
        $this->staging = $this->base.'/newrelease';
        $this->state   = $this->base.'/state';
        $this->rrmdir($this->base);

        // Fixture install at v1.0.0 (the "installed" app the updater operates on).
        $this->writeFile($this->root.'/app/Old.php', '<?php // v1.0.0');
        $this->writeFile($this->root.'/version.json', '{"version":"1.0.0"}');
        $this->writeFile($this->root.'/.env', 'APP_KEY=keepme');
        $this->writeFile($this->root.'/storage/app/user.dat', 'user-data');

        // The extracted new release (v1.1.0) — core files at the archive root.
        $this->writeFile($this->staging.'/app/New.php', '<?php // v1.1.0');
        $this->writeFile($this->staging.'/version.json', '{"version":"1.1.0"}');

        config([
            'updates.root_path'  => $this->root,
            'updates.state_path' => $this->state,
            'updates.core_paths' => ['app', 'version.json'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    #[Test]
    public function it_downloads_extracts_swaps_and_rolls_back_a_real_release(): void
    {
        // Package the new release as a real zip and serve it from a faked endpoint.
        $zip = $this->base.'/oeparts-1.1.0.zip';
        $this->makeZip($this->staging, $zip);
        $bytes = (string) file_get_contents($zip);

        Http::fake(['fake.test/*' => Http::response($bytes, 200)]);

        $manifest = [
            'version'      => '1.1.0',
            'download_url' => 'https://fake.test/oeparts-1.1.0.zip',
            'sha256'       => hash('sha256', $bytes),
            'size_bytes'   => strlen($bytes),
        ];

        // REAL download (sha256-verified) → REAL extract (zip-slip-guarded) → REAL swap.
        $downloaded = app(UpdateDownloader::class)->download($manifest);
        $this->assertSame(hash('sha256', $bytes), hash_file('sha256', $downloaded));

        $extracted = app(UpdateExtractor::class)->extract($downloaded, '1.1.0');
        $this->assertFileExists($extracted.'/app/New.php');

        app(UpdateSwapper::class)->swap($extracted, '1.1.0');

        // New code live, old gone, user data preserved.
        $this->assertFileExists($this->root.'/app/New.php');
        $this->assertFileDoesNotExist($this->root.'/app/Old.php');
        $this->assertStringContainsString('1.1.0', file_get_contents($this->root.'/version.json'));
        $this->assertSame('APP_KEY=keepme', file_get_contents($this->root.'/.env'));
        $this->assertSame('user-data', file_get_contents($this->root.'/storage/app/user.dat'));

        // REAL rollback restores the previous release exactly.
        app(UpdateSwapper::class)->rollback();
        $this->assertFileExists($this->root.'/app/Old.php');
        $this->assertFileDoesNotExist($this->root.'/app/New.php');
        $this->assertStringContainsString('1.0.0', file_get_contents($this->root.'/version.json'));
    }

    #[Test]
    public function a_failed_update_auto_rolls_back_files_and_database_through_the_real_fsm(): void
    {
        Storage::fake('local');
        config([
            'backup.disk' => 'local', 'backup.staging_disk' => 'local', 'backup.db.chunk_rows' => 500,
            // Keep finalize side-effect-free; force verification to fail so the FSM rolls back.
            'updates.post_swap' => ['artisan' => [], 'vendor_publish_tags' => [], 'seeders' => [],
                'rebuild_cache' => false, 'restart_queue' => false],
            'updates.verify' => ['required_tables' => ['a_table_that_will_never_exist'], 'referential' => [], 'smoke' => false],
        ]);

        // A row that the (simulated) bad release will corrupt after the swap.
        DB::statement('CREATE TABLE e2e_widgets (id INTEGER PRIMARY KEY, name TEXT)');
        DB::table('e2e_widgets')->insert(['id' => 1, 'name' => 'original']);

        $manifest = [
            'version'                    => '1.1.0',
            'channel'                    => 'stable',
            'min_version_to_update_from' => '1.0.0',
            'required_extensions'        => ['json'],
            'migration_count'            => 0,
        ];

        $applier = new E2EApplier;
        $applier->staging = $this->staging;

        $history = $applier->run($applier->start($manifest));

        // The FSM detected the post-swap failure and reversed EVERYTHING with real services.
        $this->assertSame(UpdateHistory::STATUS_ROLLED_BACK, $history->status);
        // Files reversed: the fixture root is back on v1.0.0.
        $this->assertFileExists($this->root.'/app/Old.php');
        $this->assertFileDoesNotExist($this->root.'/app/New.php');
        $this->assertStringContainsString('1.0.0', file_get_contents($this->root.'/version.json'));
        // Database restored from the pre-update backup: the corruption is undone.
        $this->assertSame('original', DB::table('e2e_widgets')->where('id', 1)->value('name'));
        // Maintenance lifted, lock released.
        $this->assertFalse((bool) settings('maintenance.enabled'));
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function makeZip(string $sourceDir, string $zipPath): void
    {
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDir))), '/');
                $zip->addFile($file->getPathname(), $rel);
            }
        }

        $zip->close();
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
}

/**
 * Real applier with only the network steps short-circuited to a prebuilt staging dir,
 * and a simulated bad-release data corruption during finalize — everything else (backup,
 * swap, verify, rollback, DB restore) runs for real.
 */
class E2EApplier extends UpdateApplier
{
    public string $staging;

    protected function doDownload(UpdateHistory $history): void
    {
        // Network covered by the other e2e test; here we start from the extracted release.
    }

    protected function doExtract(UpdateHistory $history): void
    {
        $history->putMeta('staging_dir', $this->staging);
        $history->save();
    }

    protected function doFinalize(UpdateHistory $history): void
    {
        // Simulate a faulty release mutating data after the swap; the post-up
        // verification then fails and the FSM must restore this from the backup.
        DB::table('e2e_widgets')->where('id', 1)->update(['name' => 'corrupted']);
    }
}
