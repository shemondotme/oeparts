<?php

namespace Tests\Unit\Cleanup;

use App\Services\Cleanup\ProtectedPath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ProtectedPath is the gate between the engine and unlink(). These tests build a fake
 * project tree and try every classic way to escape it.
 */
class ProtectedPathTest extends TestCase
{
    private string $base;

    private string $logs;

    private ProtectedPath $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-protectedpath-'.getmypid();
        $this->rrmdir($this->base);
        foreach (['storage/logs', 'storage/logs-evil', 'storage/app/updates', 'storage/app/install', 'vendor', 'app', 'public/build'] as $dir) {
            mkdir($this->base.'/'.$dir, 0775, true);
        }
        $this->logs = $this->base.'/storage/logs';

        config(['updates.state_path' => $this->base.'/storage/app/updates']);
        $this->guard = new ProtectedPath($this->base);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (is_link($dir) || ! is_dir($dir)) {
            @unlink($dir); // a file or a symlink (never followed); harmless when nothing is there

            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->rrmdir($dir.DIRECTORY_SEPARATOR.$entry);
            }
        }
        @rmdir($dir);
    }

    private function oldFile(string $path, int $ageSeconds = 7200): string
    {
        file_put_contents($path, 'x');
        touch($path, time() - $ageSeconds);

        return $path;
    }

    #[Test]
    public function an_old_regular_file_directly_inside_the_root_is_deletable(): void
    {
        $file = $this->oldFile($this->logs.'/laravel-2026-01-01.log');

        $this->assertNull($this->guard->evaluate($this->logs, $file));
    }

    #[Test]
    public function a_recently_modified_file_is_never_deletable(): void
    {
        $file = $this->oldFile($this->logs.'/today.log', 60);

        $this->assertSame('too_new', $this->guard->evaluate($this->logs, $file));
        // Even if a target asks for a shorter age, the 60 minute floor wins.
        $this->assertSame('too_new', $this->guard->evaluate($this->logs, $file, 0));
    }

    #[Test]
    public function dot_dot_traversal_cannot_leave_the_root(): void
    {
        $outside = $this->oldFile($this->base.'/storage/outside.txt');

        $this->assertSame('not_inside_root', $this->guard->evaluate($this->logs, $this->logs.'/../outside.txt'));
        $this->assertFileExists($outside);
    }

    #[Test]
    public function a_sibling_directory_sharing_the_roots_prefix_is_not_inside_it(): void
    {
        $evil = $this->oldFile($this->base.'/storage/logs-evil/secret.log');

        $this->assertSame('not_inside_root', $this->guard->evaluate($this->logs, $evil));
    }

    #[Test]
    public function a_symlink_is_never_followed_or_deleted(): void
    {
        $target = $this->oldFile($this->base.'/storage/app/updates/last-swap.json');
        $link = $this->logs.'/innocent.log';

        if (! @symlink($target, $link)) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        $this->assertSame('symlink', $this->guard->evaluate($this->logs, $link));
        $this->assertFileExists($target);
    }

    #[Test]
    public function the_recovery_net_and_secrets_are_on_the_never_touch_list(): void
    {
        foreach (['lock', 'arm.flag', 'last-swap.json', 'recovery.log'] as $name) {
            $this->assertTrue($this->guard->denies($this->base.'/storage/app/updates/'.$name), $name);
        }

        foreach ([
            '.env', 'vendor/autoload.php', 'app/Models/User.php', 'public/build/manifest.json',
            'storage/release-signing-private.key', 'storage/installed.lock', 'storage/app/install/state.json',
            '.git/config', 'composer.lock',
        ] as $relative) {
            $this->assertTrue($this->guard->denies($this->base.'/'.$relative), $relative);
        }
    }

    #[Test]
    public function a_directory_that_contains_a_protected_file_cannot_be_deleted_wholesale(): void
    {
        // Deleting `storage/app/updates` would take lock / arm.flag / last-swap.json with it.
        $this->assertTrue($this->guard->denies($this->base.'/storage/app/updates'));
        $this->assertTrue($this->guard->denies($this->base.'/storage/app'));
        $this->assertTrue($this->guard->denies($this->base.'/storage'));
        $this->assertTrue($this->guard->denies($this->base));
    }

    #[Test]
    public function ordinary_cleanup_locations_are_not_denied(): void
    {
        foreach (['storage/logs/laravel.log', 'storage/app/updates/downloads/x.zip', 'storage/app/private/backups/3', 'storage/framework/cache/data/ab'] as $relative) {
            $this->assertFalse($this->guard->denies($this->base.'/'.$relative), $relative);
        }
    }

    #[Test]
    public function a_directory_is_refused_unless_the_target_allows_directories(): void
    {
        mkdir($this->logs.'/old-run-3', 0775);
        $this->oldFile($this->logs.'/old-run-3/part.bin');
        touch($this->logs.'/old-run-3', time() - 7200);

        $this->assertSame('not_regular_file', $this->guard->evaluate($this->logs, $this->logs.'/old-run-3'));
        $this->assertNull($this->guard->evaluate($this->logs, $this->logs.'/old-run-3', 7200, false, true));
    }

    #[Test]
    public function a_directory_with_a_fresh_file_inside_counts_as_recent(): void
    {
        mkdir($this->logs.'/busy-run', 0775);
        touch($this->logs.'/busy-run', time() - 86400);
        file_put_contents($this->logs.'/busy-run/being-written.bin', 'x'); // modified just now

        $this->assertSame('too_new', $this->guard->evaluate($this->logs, $this->logs.'/busy-run', 3600, false, true));
    }

    #[Test]
    public function recursive_roots_accept_nested_files_but_flat_roots_do_not(): void
    {
        mkdir($this->logs.'/2026/10', 0775, true);
        $nested = $this->oldFile($this->logs.'/2026/10/a.log');

        $this->assertSame('not_inside_root', $this->guard->evaluate($this->logs, $nested));
        $this->assertNull($this->guard->evaluate($this->logs, $nested, 3600, true));
    }

    #[Test]
    public function a_root_outside_the_project_or_equal_to_it_is_refused(): void
    {
        $file = $this->oldFile($this->base.'/stray.txt');

        $this->assertSame('root_outside_project', $this->guard->evaluate($this->base, $file), 'the project root itself is not a valid root');
        $this->assertSame('root_outside_project', $this->guard->evaluate(sys_get_temp_dir(), $file));
        $this->assertSame('root_missing', $this->guard->evaluate($this->base.'/nope', $file));
    }

    #[Test]
    public function a_missing_file_and_a_nul_byte_are_handled(): void
    {
        $this->assertSame('missing', $this->guard->evaluate($this->logs, $this->logs.'/gone.log'));
        $this->assertSame('invalid_path', $this->guard->evaluate($this->logs, $this->logs."/a\0b.log"));
    }

    #[Test]
    public function only_plain_visible_basenames_resolve(): void
    {
        foreach (['laravel-2026-10-11.log', 'install-2026.log', '12', 'vol-0.oevol'] as $ok) {
            $this->assertTrue($this->guard->isSafeBasename($ok), $ok);
            $this->assertNotNull($this->guard->resolve($this->logs, $ok), $ok);
        }

        foreach (['', '.env', '.gitignore', '../x', 'a/b', 'a\\b', '..', 'a..b', "x\0", 'sp ace', str_repeat('a', 300)] as $bad) {
            $this->assertFalse($this->guard->isSafeBasename($bad), json_encode($bad));
            $this->assertNull($this->guard->resolve($this->logs, $bad), json_encode($bad));
        }
    }
}
