<?php

namespace Tests\Unit;

use App\Services\Cleanup\ProtectedPath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The directories that hold per-server working state (the installer's run state, the
 * migration import inbox, exported packages, the freeze / instance-mode files) describe
 * THIS server, not the site. They must be on BOTH safety lists: left out of file backups
 * (so a restore never brings another server's state) AND never deleted by the Cleanup
 * Engine (a waiting package or a freeze file is not log-like data). Two independent
 * lists drifting apart is exactly how one of them gets forgotten.
 */
class PerInstanceDirectoriesProtectedTest extends TestCase
{
    private const DIRECTORIES = [
        'storage/app/install',
        'storage/app/import',
        'storage/app/migration',
        'storage/app/instance',
    ];

    #[Test]
    public function every_per_instance_directory_is_excluded_from_file_backups(): void
    {
        $excluded = (array) config('backup.files.exclude');

        foreach (self::DIRECTORIES as $dir) {
            $this->assertContains($dir, $excluded, "{$dir} must not be archived into a backup");
        }
    }

    #[Test]
    public function every_per_instance_directory_is_on_the_cleanup_never_touch_list(): void
    {
        $denied = (new \ReflectionClassConstant(ProtectedPath::class, 'DENIED'))->getValue();

        foreach (self::DIRECTORIES as $dir) {
            $this->assertContains($dir, $denied, "{$dir} must never be deleted by the Cleanup Engine");
        }
    }
}
