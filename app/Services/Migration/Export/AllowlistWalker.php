<?php

namespace App\Services\Migration\Export;

use App\Security\PackagePathPolicy;
use App\Services\Migration\Groups\FileRoot;

/**
 * Walks the folders of user files that belong to the selected groups, and nothing else.
 *
 * "Allowlist" is literal: only the roots given are ever opened, and inside them every path must pass
 * PackagePathPolicy (deny always wins: `.env`, `.php`, key files, framework folders ...) on BOTH its
 * lexical form and its real path. Symbolic links are never followed: a link inside a media folder that
 * points at `.env` or at a folder outside the disk must not carry that file into a package. They are
 * listed as skipped with their target, so the operator sees them.
 *
 * It is resumable. A walk can be millions of entries, and each web or queue request advances it a
 * little, so the whole state is one plain array (the cursor): a stack of folders, each remembering the
 * last name it handled. Folders are read in sorted order, which makes "continue after this name" exact
 * even if files are added meanwhile (they are picked up if they sort after the cursor).
 */
final class AllowlistWalker
{
    /** Names listed in the cursor's sample of skipped files; the rest are only counted by reason. */
    public const SKIPPED_SAMPLE = 200;

    /** One folder with more entries than this cannot be read into memory safely. */
    public const MAX_ENTRIES_PER_FOLDER = 1_500_000;

    /**
     * @param  array<string, array{path: string, prefix: string}>  $disks  disk name => its absolute folder and the
     *                                                                     root-relative path of that folder (what PackagePathPolicy sees,
     *                                                                     e.g. `storage/app/public`)
     */
    public function __construct(
        private readonly PackagePathPolicy $policy,
        private readonly array $disks,
    ) {}

    /**
     * A new walk over these roots.
     *
     * @param  array<int, FileRoot>  $roots
     * @return array<string, mixed> the cursor
     */
    public function start(array $roots): array
    {
        $pending = [];

        foreach ($roots as $root) {
            if (! isset($this->disks[$root->disk])) {
                throw new \InvalidArgumentException("There is no disk called '{$root->disk}'.");
            }

            $pending[] = ['disk' => $root->disk, 'dir' => $root->path];
        }

        return [
            'pending_roots' => $pending,
            'stack' => [],
            'done' => $pending === [],
            'stats' => ['files' => 0, 'bytes' => 0, 'folders' => 0],
            'skipped_counts' => [],
            'skipped_sample' => [],
        ];
    }

    /**
     * Advance a walk by up to $maxEntries directory entries.
     *
     * @param  array<string, mixed>  $cursor
     * @param  callable(FileEntry): void  $emit  called once for every file that may travel
     * @return array<string, mixed> the new cursor
     */
    public function step(array $cursor, int $maxEntries, callable $emit): array
    {
        $budget = max(1, $maxEntries);

        while ($budget > 0 && ! $cursor['done']) {
            if ($cursor['stack'] === []) {
                if ($cursor['pending_roots'] === []) {
                    $cursor['done'] = true;
                    break;
                }

                $root = array_shift($cursor['pending_roots']);

                if ($this->openRoot($root['disk'], $root['dir'], $cursor)) {
                    $cursor['stack'][] = ['disk' => $root['disk'], 'dir' => $root['dir'], 'after' => null];
                    $cursor['stats']['folders']++;
                }

                continue;
            }

            $top = count($cursor['stack']) - 1;
            $frame = $cursor['stack'][$top];
            $names = $this->listFolder($frame['disk'], $frame['dir']);
            $next = $this->nextName($names, $frame['after']);

            if ($next === null) {
                array_pop($cursor['stack']);

                continue;
            }

            $cursor['stack'][$top]['after'] = $next;
            $budget--;

            $this->handle($frame['disk'], $frame['dir'], $next, $cursor, $emit);
        }

        if ($cursor['stack'] === [] && $cursor['pending_roots'] === []) {
            $cursor['done'] = true;
        }

        return $cursor;
    }

    /**
     * Walk to the end in one call (for small trees and tests).
     *
     * @param  array<int, FileRoot>  $roots
     * @return array{files: array<int, FileEntry>, cursor: array<string, mixed>}
     */
    public function all(array $roots): array
    {
        $files = [];
        $cursor = $this->start($roots);

        while (! $cursor['done']) {
            $cursor = $this->step($cursor, 10_000, function (FileEntry $entry) use (&$files): void {
                $files[] = $entry;
            });
        }

        return ['files' => $files, 'cursor' => $cursor];
    }

    // ------------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $cursor
     */
    private function openRoot(string $disk, string $dir, array &$cursor): bool
    {
        $absolute = $this->absolute($disk, $dir);

        if (! is_dir($absolute)) {
            return false; // a group with no files yet is not a problem
        }

        if (is_link($absolute)) {
            $this->skip($cursor, $disk.':'.$dir, 'symlink', ['target' => (string) readlink($absolute)]);

            return false;
        }

        // The root itself must be allowed (a root such as `local:invoices` is a forbidden folder).
        $rejection = $this->policy->reject($this->lexical($disk, $dir), [$this->lexical($disk, $dir)]);
        if ($rejection !== null) {
            $this->skip($cursor, $disk.':'.$dir, $rejection->value);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $cursor
     * @param  callable(FileEntry): void  $emit
     */
    private function handle(string $disk, string $dir, string $name, array &$cursor, callable $emit): void
    {
        $relative = $dir.'/'.$name;
        $label = $disk.':'.$relative;
        $absolute = $this->absolute($disk, $relative);

        if (is_link($absolute)) {
            $this->skip($cursor, $label, 'symlink', ['target' => (string) @readlink($absolute)]);

            return;
        }

        $lexical = $this->lexical($disk, $relative);
        $rejection = $this->policy->reject($lexical, [$this->lexical($disk, $this->rootOf($relative))]);

        if ($rejection !== null) {
            $this->skip($cursor, $label, $rejection->value);

            return;
        }

        if (is_dir($absolute)) {
            if (! $this->staysInside($disk, $absolute)) {
                $this->skip($cursor, $label, 'outside_root');

                return;
            }

            $cursor['stack'][] = ['disk' => $disk, 'dir' => $relative, 'after' => null];
            $cursor['stats']['folders']++;

            return;
        }

        if (! is_file($absolute)) {
            $this->skip($cursor, $label, 'not_regular_file');

            return;
        }

        $real = realpath($absolute);
        if ($real === false || ! $this->staysInside($disk, $real) || $this->policy->reject($this->lexicalOfReal($disk, $real)) !== null) {
            $this->skip($cursor, $label, 'outside_root');

            return;
        }

        $size = @filesize($absolute);
        $mtime = @filemtime($absolute);

        if ($size === false || $mtime === false) {
            $this->skip($cursor, $label, 'unreadable');

            return;
        }

        $cursor['stats']['files']++;
        $cursor['stats']['bytes'] += $size;

        $emit(new FileEntry($disk, $relative, $size, $mtime));
    }

    /**
     * @param  array<string, mixed>  $cursor
     * @param  array<string, string>  $detail
     */
    private function skip(array &$cursor, string $label, string $reason, array $detail = []): void
    {
        $cursor['skipped_counts'][$reason] = ($cursor['skipped_counts'][$reason] ?? 0) + 1;

        if (count($cursor['skipped_sample']) < self::SKIPPED_SAMPLE) {
            $cursor['skipped_sample'][] = ['path' => $label, 'reason' => $reason] + $detail;
        }
    }

    /** @return array<int, string> the sorted names of a folder */
    private function listFolder(string $disk, string $dir): array
    {
        $absolute = $this->absolute($disk, $dir);
        $handle = @opendir($absolute);

        if ($handle === false) {
            return [];
        }

        $names = [];

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $names[] = $entry;

            if (count($names) > self::MAX_ENTRIES_PER_FOLDER) {
                closedir($handle);

                throw new \RuntimeException("The folder {$disk}:{$dir} holds more than ".self::MAX_ENTRIES_PER_FOLDER.' entries; split it before exporting.');
            }
        }

        closedir($handle);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @param  array<int, string>  $names  sorted
     */
    private function nextName(array $names, ?string $after): ?string
    {
        if ($after === null) {
            return $names[0] ?? null;
        }

        $low = 0;
        $high = count($names);

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);

            if (strcmp($names[$mid], $after) <= 0) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $names[$low] ?? null;
    }

    private function absolute(string $disk, string $relative): string
    {
        return rtrim($this->disks[$disk]['path'], '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /** The path as PackagePathPolicy sees it: relative to the application root, with forward slashes. */
    private function lexical(string $disk, string $relative): string
    {
        return trim($this->disks[$disk]['prefix'], '/').'/'.$relative;
    }

    private function lexicalOfReal(string $disk, string $real): string
    {
        $base = realpath($this->disks[$disk]['path']);
        $inside = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($real, strlen((string) $base))), '/');

        return trim($this->disks[$disk]['prefix'], '/').'/'.$inside;
    }

    private function staysInside(string $disk, string $realOrAbsolute): bool
    {
        $base = realpath($this->disks[$disk]['path']);
        $real = realpath($realOrAbsolute);

        return $base !== false && $real !== false && ($real === $base || str_starts_with($real, $base.DIRECTORY_SEPARATOR));
    }

    /** The first segment of a root-relative path: the root folder it lives in. */
    private function rootOf(string $relative): string
    {
        return explode('/', $relative)[0];
    }
}
