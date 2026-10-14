<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationPaths;

/**
 * A small semaphore for package downloads, made of lock files (no cache, no database: it must work on a site with neither
 * a cache server nor a database to spare, and a process that dies frees its slot by itself because the operating system
 * drops its lock). PHP cannot see how many workers the web server has, so the default is ONE download at a time.
 */
final class ShareSlots
{
    public function __construct(private readonly ?string $directory = null) {}

    private function directory(): string
    {
        return $this->directory ?? MigrationPaths::root().DIRECTORY_SEPARATOR.'share-slots';
    }

    /**
     * Take a free slot without waiting.
     *
     * @return resource|null a handle to hand back to release(), or null when every slot is taken
     */
    public function acquire()
    {
        MigrationPaths::ensureDirectory($this->directory());

        for ($n = 1; $n <= max(1, (int) config('migration.share.max_streams', 1)); $n++) {
            $handle = @fopen($this->directory().DIRECTORY_SEPARATOR.'slot-'.$n.'.lock', 'c');

            if ($handle === false) {
                continue;
            }

            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }

            fclose($handle);
        }

        return null;
    }

    /** @param  resource  $handle */
    public function release($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
