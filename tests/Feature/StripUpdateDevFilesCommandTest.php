<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * oeparts:update:strip-dev-files — manual recovery for a git-managed install
 * whose update was killed from outside PHP between `git checkout --force`
 * landing and GitUpdater::stripDevFilesFromWorkingTree() running (one
 * request, no checkpoint between them — confirmed live). isGitManaged() and
 * the strip itself are pure filesystem checks, so a REAL git repo isn't
 * needed here (GitUpdaterTest already proves checkout() calls this
 * correctly against one) — just a root_path with the exact tracked-but-
 * excluded files an interrupted checkout would have left behind.
 */
class StripUpdateDevFilesCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-stripcmd-'.getmypid();
        @mkdir($this->root, 0775, true);
        config(['updates.root_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    #[Test]
    public function it_removes_dev_only_files_an_interrupted_checkout_left_behind(): void
    {
        // Simulates checkout() having landed (git checkout --force writes every
        // tracked file in one go) but having been killed before its own cleanup
        // step ran — exactly what a production install was found with.
        @mkdir($this->root.'/.git', 0775, true);
        @mkdir($this->root.'/tests', 0775, true);
        file_put_contents($this->root.'/tests/Foo.php', 'dev only');
        file_put_contents($this->root.'/phpstan.neon', 'dev only');
        file_put_contents($this->root.'/.env', 'APP_KEY=keep-me'); // must survive
        @mkdir($this->root.'/storage', 0775, true);
        file_put_contents($this->root.'/storage/keep.txt', 'keep me too');

        $this->artisan('oeparts:update:strip-dev-files')
            ->expectsOutputToContain('2 dev-only path(s) removed')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->root.'/tests');
        $this->assertFileDoesNotExist($this->root.'/phpstan.neon');
        $this->assertFileExists($this->root.'/.env', 'the real .env must never be touched');
        $this->assertSame('APP_KEY=keep-me', file_get_contents($this->root.'/.env'));
        $this->assertFileExists($this->root.'/storage/keep.txt', 'storage must never be touched');
        $this->assertDirectoryExists($this->root.'/.git', '.git itself must never be stripped');
    }

    #[Test]
    public function it_is_a_no_op_on_an_already_clean_install(): void
    {
        @mkdir($this->root.'/.git', 0775, true);

        $this->artisan('oeparts:update:strip-dev-files')
            ->expectsOutputToContain('already clean')
            ->assertSuccessful();
    }

    #[Test]
    public function it_does_nothing_on_a_zip_install(): void
    {
        // No .git directory — GitUpdater::isGitManaged() is false, and a zip
        // release never contained these files to begin with.
        file_put_contents($this->root.'/phpstan.neon', 'should never be touched on a zip install');

        $this->artisan('oeparts:update:strip-dev-files')
            ->expectsOutputToContain('not git-managed')
            ->assertSuccessful();

        $this->assertFileExists($this->root.'/phpstan.neon');
    }
}
