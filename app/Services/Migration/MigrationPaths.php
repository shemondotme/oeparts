<?php

namespace App\Services\Migration;

/**
 * Where the migration feature keeps its files. One tree under storage/app/migration, plain
 * filesystem (not a Storage disk), so it works while the installer has no database and is easy to
 * exclude from backups and protect from the Cleanup Engine as a whole (both already list it).
 */
final class MigrationPaths
{
    public static function root(): string
    {
        return storage_path('app/migration');
    }

    /** Keys wrapped with the site key, one file per run (KeyEscrow). */
    public static function escrow(): string
    {
        return self::root().DIRECTORY_SEPARATOR.'escrow';
    }

    /** Wrong-passphrase counters (PassphraseThrottle). */
    public static function throttle(): string
    {
        return self::root().DIRECTORY_SEPARATOR.'throttle';
    }

    /** Create a private directory (0700) if it is not there yet. */
    public static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create the directory {$directory}.");
        }
    }
}
