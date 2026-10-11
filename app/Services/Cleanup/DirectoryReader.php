<?php

namespace App\Services\Cleanup;

use Generator;
use SplFileInfo;

/**
 * Lists a directory with plain opendir()/readdir() — deliberately NOT PHP's SPL DirectoryIterator /
 * FilesystemIterator / RecursiveDirectoryIterator.
 *
 * Found in the real-MySQL/real-filesystem rehearsal: on a Docker Desktop bind mount the SPL iterators silently
 * dropped the first 1 021 entries of a 13 000-entry folder (rewinddir() after the first getdents batch loses that
 * batch on some virtual/network filesystems), while readdir()/scandir() returned all of them. A cleanup that quietly
 * skips part of a folder reports a clean result for work it never saw, so every directory walk in the engine goes
 * through this reader instead.
 *
 * Streams entries (constant memory, unlike scandir() on a folder with millions of files). Symlinks are returned as
 * entries but never descended into. Order is whatever the filesystem returns; callers must not depend on it staying
 * identical between two walks of a folder that is changing.
 */
final class DirectoryReader
{
    /**
     * Direct children (files, folders, links), as bare names.
     *
     * @return Generator<int, string>
     */
    public static function names(string $dir): Generator
    {
        $handle = @opendir($dir);
        if ($handle === false) {
            return;
        }

        try {
            while (($name = readdir($handle)) !== false) {
                if ($name !== '.' && $name !== '..') {
                    yield $name;
                }
            }
        } finally {
            closedir($handle);
        }
    }

    /**
     * Direct children as SplFileInfo.
     *
     * @return Generator<int, SplFileInfo>
     */
    public static function children(string $dir): Generator
    {
        $base = rtrim($dir, '/\\');

        foreach (self::names($dir) as $name) {
            yield new SplFileInfo($base.DIRECTORY_SEPARATOR.$name);
        }
    }

    /**
     * Every file below $dir at any depth (leaves only). A symlink — to a file or to a folder — is yielded as an
     * entry but a linked FOLDER is never entered.
     *
     * @return Generator<int, SplFileInfo>
     */
    public static function leaves(string $dir): Generator
    {
        $stack = [rtrim($dir, '/\\')];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach (self::children($current) as $entry) {
                if ($entry->isLink() || ! $entry->isDir()) {
                    yield $entry;
                } else {
                    $stack[] = $entry->getPathname();
                }
            }
        }
    }
}
