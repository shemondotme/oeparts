<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * oeparts:build command (Module 21, Chunk 5.1). Runs against a fixture export dir and
 * proves the safety guards (requires --path; refuses the live repo root).
 */
class BuildReleaseCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        config(['updates.build' => [
            'exclude' => ['tests', '.env', 'CLAUDE.md'],
            'manifest_file' => 'file-manifest.json',
            'licenses_file' => 'THIRD-PARTY-LICENSES.md',
        ]]);

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-buildcmd-'.getmypid();
        $this->rrmdir($this->dir);
        $this->writeFile('app/Foo.php', '<?php // foo');
        $this->writeFile('version.json', '{"version":"2.0.0"}');
        $this->writeFile('tests/SomeTest.php', '<?php // test');
        $this->writeFile('.env', 'APP_KEY=secret');
        $this->writeFile('vendor/acme/pkg/LICENSE', 'MIT');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
        parent::tearDown();
    }

    #[Test]
    public function it_prepares_an_export_directory(): void
    {
        $this->artisan('oeparts:build', ['--path' => $this->dir])
            ->assertExitCode(0);

        $this->assertFileExists($this->dir.'/file-manifest.json');
        $this->assertFileExists($this->dir.'/THIRD-PARTY-LICENSES.md');
        // Dev files stripped, core kept.
        $this->assertFileDoesNotExist($this->dir.'/tests/SomeTest.php');
        $this->assertFileDoesNotExist($this->dir.'/.env');
        $this->assertFileExists($this->dir.'/app/Foo.php');
    }

    #[Test]
    public function it_refuses_without_a_path(): void
    {
        $this->artisan('oeparts:build')->assertExitCode(1);
    }

    #[Test]
    public function it_refuses_to_build_against_the_live_project_root(): void
    {
        $this->artisan('oeparts:build', ['--path' => base_path()])->assertExitCode(1);

        // Proof it did NOT strip the live tree.
        $this->assertFileExists(base_path('artisan'));
        $this->assertDirectoryExists(base_path('tests'));
    }

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
