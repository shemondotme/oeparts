<?php

namespace App\Services\Cleanup\Targets;

use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\DirectoryReader;
use App\Services\Cleanup\ProtectedPath;
use Illuminate\Support\Facades\Log;
use SplFileInfo;
use Throwable;

/**
 * Base class for "delete leftover files/folders under a registered root" targets.
 *
 * The engine never accepts a path from a request. A subclass names its registered root(s); every candidate
 * is then run through {@see ProtectedPath} (traversal, symlinks, never-touch list, minimum age) before it is
 * counted, and AGAIN right before it is removed.
 *
 * Phases, all resumable inside the checkpoint:
 *   plan    walks the root(s) and writes every qualifying entry as one JSON line to a work file
 *           (storage/framework/cleanup/). The checkpoint only stores offsets and counters, never the list.
 *   delete  reads that work file from the saved byte offset, re-checks each entry, removes it, and counts what
 *           was REALLY removed and how many bytes it freed (lstat before unlink).
 *   verify  walks the root(s) again with the same rules and the same frozen "now" and counts what still qualifies.
 *
 * Freezing `now` at the start means an entry that crosses the age threshold DURING the run is not mistaken for
 * something the run failed to delete.
 */
abstract class FileSweepTarget implements CleanupTarget
{
    /** Entries inspected per directory-scan tick before re-checking the clock. */
    private const SCAN_BATCH = 200;

    public function __construct(private readonly ?ProtectedPath $protectedPath = null) {}

    /** @return list<string> absolute paths of the registered roots (each must be inside the project) */
    abstract protected function roots(): array;

    /** Basename filter (cheap, runs before any filesystem or database check). */
    protected function matches(string $basename): bool
    {
        return true;
    }

    /** Extra rule beyond the age/safety checks (a database lookup, the file's content, ...). */
    protected function eligible(string $absPath, string $basename, int $now): bool
    {
        return true;
    }

    /** Walk below the root instead of only its direct children. */
    protected function recursive(): bool
    {
        return false;
    }

    /** Treat a matching DIRECTORY as one unit to remove (a backup run folder, a staging dir). */
    protected function allowDirs(): bool
    {
        return false;
    }

    /** The shortest age (seconds) an entry needs; rules like "a session lives 120 minutes" raise it. */
    protected function minAgeSeconds(CleanupContext $ctx, int $now): int
    {
        return max(0, $now - $ctx->cutoff->getTimestamp());
    }

    /**
     * The safety verdict for one entry (null = fine). The default is the full deletion gate; a target that
     * truncates files in place overrides it.
     */
    protected function evaluateEntry(ProtectedPath $protected, string $root, string $path, int $minAge, int $now): ?string
    {
        return $protected->evaluate($root, $path, $minAge, $this->recursive(), $this->allowDirs(), $now);
    }

    /** Whether a planned entry still needs removing (default: it still exists). A truncating target overrides this. */
    protected function stillPending(string $path): bool
    {
        return file_exists($path) || is_link($path);
    }

    public function kind(): string
    {
        return 'file';
    }

    public function truncatable(): bool
    {
        return false;
    }

    public function reportOnly(): bool
    {
        return false;
    }

    public function totalRows(): ?int
    {
        return null; // the over-delete percentage guard is about database tables
    }

    public function begin(CleanupContext $ctx): array
    {
        $work = $this->workFile($ctx);
        @unlink($work); // a stale file from an earlier attempt of the same run

        return ['now' => time(), 'plan' => ['seen' => 0, 'lines' => 0, 'bytes' => 0, 'list_bytes' => 0], 'del' => ['offset' => 0, 'deleted' => 0, 'failed' => 0, 'apparent' => 0, 'allocated' => 0], 'ver' => ['offset' => 0, 'count' => 0]];
    }

    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $work = $this->workFile($ctx);

        // A preview only counts, so it needs no work file at all (and therefore no writable work directory).
        if ($ctx->dryRun) {
            $handle = fopen('php://temp', 'c+');
        } else {
            $dir = dirname($work);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException("Cannot create the cleanup work directory {$dir}");
            }

            $handle = fopen($work, 'c+');
        }

        if ($handle === false) {
            throw new \RuntimeException("Cannot open the work file {$work}");
        }

        try {
            // A crash between writing lines and saving the checkpoint would leave lines the checkpoint does not
            // know about; cut the file back to the last checkpointed size so none is ever duplicated.
            ftruncate($handle, (int) $cp['plan']['list_bytes']);
            fseek($handle, 0, SEEK_END);

            $plan = &$cp['plan'];
            $dryRun = $ctx->dryRun;
            $done = $this->scan($ctx, (int) $cp['now'], (int) $plan['seen'], $deadline, function (string $root, SplFileInfo $entry) use ($handle, &$plan, $dryRun) {
                $size = $this->entrySize($entry);
                // A preview only counts; it must leave no work file behind.
                if (! $dryRun) {
                    fwrite($handle, json_encode(['r' => $root, 'p' => $entry->getPathname(), 's' => $size], JSON_UNESCAPED_SLASHES)."\n");
                }
                $plan['lines']++;
                $plan['bytes'] += $size;
            }, $plan['seen']);

            fflush($handle);
            $plan['list_bytes'] = (int) fstat($handle)['size'];
            unset($plan);
        } finally {
            fclose($handle);
        }

        if ($done) {
            $cp['targeted'] = (int) $cp['plan']['lines'];
            // For a preview this is "what a real run would free".
            $cp['bytes_apparent'] = (int) $cp['plan']['bytes'];
        }

        return $done;
    }

    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $work = $this->workFile($ctx);
        if (! is_file($work)) {
            return true; // nothing was found
        }

        $handle = fopen($work, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot read the work file {$work}");
        }

        $protected = $this->protectedPath();
        $now = (int) $cp['now'];
        $del = &$cp['del'];

        try {
            fseek($handle, (int) $del['offset']);

            while (($line = fgets($handle)) !== false) {
                if ($ctx->cancelRequested()) {
                    return false;
                }

                $entry = json_decode($line, true);
                if (is_array($entry) && isset($entry['r'], $entry['p'])) {
                    $this->removeOne($ctx, $protected, (string) $entry['r'], (string) $entry['p'], $now, $del);
                }

                $del['offset'] = (int) ftell($handle);
                $cp['deleted'] = (int) $del['deleted'];
                $cp['failed'] = (int) $del['failed'];

                if (microtime(true) >= $deadline) {
                    return false;
                }
            }
        } finally {
            fclose($handle);
        }

        $cp['deleted'] = (int) $del['deleted'];
        $cp['failed'] = (int) $del['failed'];
        $cp['bytes_apparent'] = (int) $del['apparent'];
        $cp['bytes_allocated'] = (int) $del['allocated'];
        unset($del);

        return true;
    }

    /**
     * Counts how many of the PLANNED entries still exist.
     *
     * Deliberately not a second walk of the folder: a walk's order and completeness cannot be trusted on a folder that
     * is changing (new files shift positions) or on a filesystem with quirky readdir, and a second walk would then report
     * entries that were never targeted as "left over". Asking about exactly the entries we meant to remove is a precise,
     * independent measurement of whether each one is gone.
     */
    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $work = $this->workFile($ctx);
        $ver = &$cp['ver'];

        if (is_file($work)) {
            $handle = fopen($work, 'rb');
            if ($handle === false) {
                throw new \RuntimeException("Cannot read the work file {$work}");
            }

            try {
                fseek($handle, (int) $ver['offset']);

                while (($line = fgets($handle)) !== false) {
                    $entry = json_decode($line, true);
                    if (is_array($entry) && isset($entry['p'])) {
                        clearstatcache(true, (string) $entry['p']);
                        if ($this->stillPending((string) $entry['p'])) {
                            $ver['count']++;
                        }
                    }

                    $ver['offset'] = (int) ftell($handle);

                    if (microtime(true) >= $deadline) {
                        unset($ver);

                        return false;
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        $cp['remaining'] = (int) $ver['count'];
        unset($ver);
        $cp['late_rows'] = 0;
        $cp['bytes_apparent'] = (int) ($cp['del']['apparent'] ?? 0);
        $cp['bytes_allocated'] = (int) ($cp['del']['allocated'] ?? 0);
        @unlink($work);

        return true;
    }

    /* ------------------------------------------------------------------ *
     * Scanning
     * ------------------------------------------------------------------ */

    /**
     * Walk the registered roots in a stable order, skipping the first $skip entries (already scanned in an
     * earlier tick), calling $onHit for every entry that qualifies. $seen is advanced as entries are consumed.
     *
     * @param  callable(string, SplFileInfo):void  $onHit
     * @return bool true when every root has been fully walked
     */
    private function scan(CleanupContext $ctx, int $now, int $skip, float $deadline, callable $onHit, int &$seen): bool
    {
        $protected = $this->protectedPath();
        $minAge = $this->minAgeSeconds($ctx, $now);
        $position = 0;
        $sinceClock = 0;

        foreach ($this->roots() as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach ($this->entries($root) as $entry) {
                if ($position++ < $skip) {
                    continue;
                }

                $seen = $position;
                $path = $entry->getPathname();

                if ($this->matches($entry->getFilename())
                    && $this->evaluateEntry($protected, $root, $path, $minAge, $now) === null
                    && $this->eligible($path, $entry->getFilename(), $now)) {
                    $onHit($root, $entry);
                }

                if (++$sinceClock >= self::SCAN_BATCH) {
                    $sinceClock = 0;
                    if (microtime(true) >= $deadline) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /** @return iterable<SplFileInfo> */
    private function entries(string $root): iterable
    {
        return $this->recursive() && ! $this->allowDirs()
            ? DirectoryReader::leaves($root)
            : DirectoryReader::children($root);
    }

    /* ------------------------------------------------------------------ *
     * Removing
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, int>  $del
     */
    private function removeOne(CleanupContext $ctx, ProtectedPath $protected, string $root, string $path, int $now, array &$del): void
    {
        // Safety is decided again at the moment of deletion, not trusted from the plan: the entry may have
        // been swapped for a symlink, or modified, in the meantime.
        $verdict = $this->evaluateEntry($protected, $root, $path, $this->minAgeSeconds($ctx, $now), $now);

        if ($verdict === 'missing') {
            return; // already gone (removed by someone else, or on an earlier pass before a crash)
        }

        if ($verdict !== null) {
            Log::channel('cleanup')->warning("Cleanup refused to remove {$path}: {$verdict}");
            $del['failed']++;

            return;
        }

        try {
            [$ok, $apparent, $allocated] = $this->remove($path, $protected);
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning("Cleanup could not remove {$path}: ".$e->getMessage());
            $del['failed']++;

            return;
        }

        if ($ok) {
            $del['deleted']++;
            $del['apparent'] += $apparent;
            $del['allocated'] += $allocated;
        } else {
            $del['failed']++;
        }
    }

    /**
     * Remove ONE entry (a file, or a whole directory) and report whether it is really gone.
     *
     * @return array{0:bool,1:int,2:int} [removed, apparent bytes, allocated bytes]
     */
    protected function remove(string $path, ProtectedPath $protected): array
    {
        clearstatcache(true, $path);

        if (is_dir($path) && ! is_link($path)) {
            return $this->removeTree($path, $protected);
        }

        $stat = @lstat($path) ?: [];
        $size = (int) ($stat['size'] ?? 0);
        $blocks = isset($stat['blocks']) && $stat['blocks'] >= 0 ? (int) $stat['blocks'] * 512 : $size;

        @unlink($path);
        clearstatcache(true, $path);

        // Verify, never assume: ReleaseBuilder::rrmdir once swallowed failures and reported success.
        return file_exists($path) || is_link($path) ? [false, 0, 0] : [true, $size, $blocks];
    }

    /**
     * Remove a directory and everything in it, never following a symlink and never touching anything on the
     * never-touch list.
     *
     * @return array{0:bool,1:int,2:int}
     */
    private function removeTree(string $dir, ProtectedPath $protected): array
    {
        if ($protected->denies($dir)) {
            return [false, 0, 0];
        }

        $apparent = 0;
        $allocated = 0;
        $clean = $this->removeChildren($dir, $protected, $apparent, $allocated);

        $removed = $clean && @rmdir($dir);
        clearstatcache(true, $dir);

        return $removed && ! file_exists($dir) ? [true, $apparent, $allocated] : [false, $apparent, $allocated];
    }

    /** Empty one directory depth-first; returns false when anything could not be removed or is protected. */
    private function removeChildren(string $dir, ProtectedPath $protected, int &$apparent, int &$allocated): bool
    {
        $clean = true;

        foreach (DirectoryReader::children($dir) as $child) {
            $path = $child->getPathname();

            if ($protected->denies($path)) {
                $clean = false;

                continue;
            }

            if ($child->isLink() || ! $child->isDir()) {
                $stat = @lstat($path) ?: [];
                $size = (int) ($stat['size'] ?? 0);
                $blocks = isset($stat['blocks']) && $stat['blocks'] >= 0 ? (int) $stat['blocks'] * 512 : $size;

                if (@unlink($path) || ! file_exists($path)) {
                    $apparent += $size;
                    $allocated += $blocks;
                } else {
                    $clean = false;
                }

                continue;
            }

            // A real sub-folder: empty it, then remove it.
            if (! $this->removeChildren($path, $protected, $apparent, $allocated) || ! @rmdir($path)) {
                $clean = false;
            }
        }

        return $clean;
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    private function entrySize(SplFileInfo $entry): int
    {
        if ($entry->isLink() || ! $entry->isDir()) {
            return (int) @$entry->getSize();
        }

        $total = 0;
        try {
            foreach (DirectoryReader::leaves($entry->getPathname()) as $child) {
                if (! $child->isLink() && $child->isFile()) {
                    $total += (int) @$child->getSize();
                }
            }
        } catch (Throwable $e) {
            Log::channel('cleanup')->notice("Could not size {$entry->getPathname()}: ".$e->getMessage());
        }

        return $total;
    }

    protected function protectedPath(): ProtectedPath
    {
        return $this->protectedPath ?? app(ProtectedPath::class);
    }

    private function workFile(CleanupContext $ctx): string
    {
        return storage_path('framework/cleanup/'.$ctx->run->getKey().'-'.$this->key().'.ndjson');
    }
}
