<?php

namespace Tests\Feature;

use App\Services\Updates\ReleaseBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Release build core (Module 21, Chunk 5.1). Drives the ReleaseBuilder against a
 * fixture export directory so the strip list, per-file sha256 manifest, license
 * bundling, and modified-core verification are all exercised without touching the
 * live repo (the command guards against that separately).
 */
class ReleaseBuilderTest extends TestCase
{
    private string $dir;

    private array $config = [
        'exclude' => ['.git', 'tests', 'node_modules', '.env', 'storage/app/updates', 'CLAUDE.md'],
        'manifest_file' => 'file-manifest.json',
        'licenses_file' => 'THIRD-PARTY-LICENSES.md',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-build-'.getmypid();
        $this->rrmdir($this->dir);

        $this->writeFile('app/Foo.php', '<?php // foo');
        $this->writeFile('config/app.php', 'return [];');
        $this->writeFile('version.json', '{"version":"1.2.3"}');
        $this->writeFile('.env', 'APP_KEY=secret');
        $this->writeFile('.env.example', 'APP_KEY=');
        $this->writeFile('CLAUDE.md', '# internal');
        $this->writeFile('tests/SomeTest.php', '<?php // test');
        $this->writeFile('.git/config', '[core]');
        $this->writeFile('node_modules/pkg/index.js', 'module.exports={}');
        $this->writeFile('storage/app/updates/state.json', '{}');
        $this->writeFile('storage/app/public/keep.txt', 'keep me');
        $this->writeFile('vendor/acme/pkg/LICENSE', "MIT License\n\nPermission is hereby granted...");
        $this->writeFile('vendor/acme/pkg/src/File.php', '<?php // vendor');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
        parent::tearDown();
    }

    private function builder(): ReleaseBuilder
    {
        return new ReleaseBuilder($this->config);
    }

    #[Test]
    public function strip_removes_dev_and_secret_paths_but_keeps_core(): void
    {
        $removed = $this->builder()->stripDevFiles($this->dir);

        foreach (['.git', 'tests', 'node_modules', '.env', 'storage/app/updates', 'CLAUDE.md'] as $rel) {
            $this->assertContains($rel, $removed);
            $this->assertFileDoesNotExist($this->dir.'/'.$rel);
        }

        // Core + .env.example (needed for new_env_keys diffing) + user media survive.
        $this->assertFileExists($this->dir.'/app/Foo.php');
        $this->assertFileExists($this->dir.'/config/app.php');
        $this->assertFileExists($this->dir.'/version.json');
        $this->assertFileExists($this->dir.'/.env.example');
        $this->assertFileExists($this->dir.'/storage/app/public/keep.txt');
        $this->assertFileExists($this->dir.'/vendor/acme/pkg/LICENSE');
    }

    #[Test]
    public function manifest_lists_shipped_files_with_sha256_and_excludes_dev_and_itself(): void
    {
        $manifest = $this->builder()->buildFileManifest($this->dir);

        $this->assertSame('1.2.3', $manifest['version']);
        $this->assertArrayHasKey('app/Foo.php', $manifest['files']);
        $this->assertSame(
            hash('sha256', '<?php // foo'),
            $manifest['files']['app/Foo.php']['sha256']
        );

        // Excluded paths + the manifest itself never appear.
        foreach (['tests/SomeTest.php', '.env', 'CLAUDE.md', 'node_modules/pkg/index.js', 'file-manifest.json'] as $rel) {
            $this->assertArrayNotHasKey($rel, $manifest['files']);
        }
        // Shipped extras are listed.
        $this->assertArrayHasKey('.env.example', $manifest['files']);
        $this->assertArrayHasKey('vendor/acme/pkg/LICENSE', $manifest['files']);

        $this->assertSame(count($manifest['files']), $manifest['file_count']);
        $this->assertFileExists($this->dir.'/file-manifest.json');
    }

    #[Test]
    public function verify_detects_changed_and_missing_files(): void
    {
        $this->builder()->buildFileManifest($this->dir);

        file_put_contents($this->dir.'/app/Foo.php', '<?php // TAMPERED');
        @unlink($this->dir.'/config/app.php');

        $report = $this->builder()->verifyAgainstManifest($this->dir);

        $this->assertContains('app/Foo.php', $report['changed']);
        $this->assertContains('config/app.php', $report['missing']);
    }

    #[Test]
    public function bundle_licenses_collects_vendor_license_text(): void
    {
        $count = $this->builder()->bundleLicenses($this->dir);

        $this->assertSame(1, $count);
        $bundle = $this->dir.'/THIRD-PARTY-LICENSES.md';
        $this->assertFileExists($bundle);
        $contents = file_get_contents($bundle);
        $this->assertStringContainsString('acme/pkg', $contents);
        $this->assertStringContainsString('Permission is hereby granted', $contents);
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function writeFile(string $rel, string $contents): void
    {
        $path = $this->dir.'/'.$rel;
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
