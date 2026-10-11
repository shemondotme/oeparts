<?php

namespace App\Services\Cleanup;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The file-log list on the Cleanup page: show what is in the log directory and delete or empty chosen files.
 * (Viewing and downloading stays in the Log Viewer page; this is only about reclaiming space.)
 *
 * The client only ever sends BASENAMES. Each one is resolved against the one registered log directory and run
 * through {@see ProtectedPath} before anything is touched, so a request can never name a path of its own.
 * A file modified in the last hour is "active": it cannot be deleted (a logger may hold it open) but it can be
 * emptied in place, which frees the space without disturbing the writer.
 *
 * Every call reports exactly what happened per file and writes ONE audit row.
 */
final class FileLogManager
{
    public function __construct(
        private readonly ProtectedPath $protectedPath,
        private readonly CleanupAudit $audit,
    ) {}

    public function directory(): string
    {
        return rtrim((string) config('cleanup.log_dir', storage_path('logs')), '/\\');
    }

    /**
     * Newest first.
     *
     * @return list<array{name:string, bytes:int, modified:int, active:bool, deletable:bool, oversized:bool}>
     */
    public function files(): array
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            return [];
        }

        $rows = [];
        foreach (DirectoryReader::children($dir) as $entry) {
            $name = $entry->getFilename();

            if (! $entry->isFile() || $entry->isLink() || ! $this->protectedPath->isSafeBasename($name) || ! str_ends_with($name, '.log')) {
                continue;
            }

            $path = $entry->getPathname();
            $rows[] = [
                'name' => $name,
                'bytes' => (int) $entry->getSize(),
                'modified' => (int) $entry->getMTime(),
                'active' => (time() - (int) $entry->getMTime()) < ProtectedPath::MIN_AGE_SECONDS,
                'deletable' => $this->protectedPath->evaluate($dir, $path) === null,
                'oversized' => (int) $entry->getSize() >= Targets\ConfiguredFileTarget::OVERSIZED_LOG_BYTES,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['modified'] <=> $a['modified']);

        return $rows;
    }

    /**
     * Delete the chosen files.
     *
     * @param  list<string>  $basenames
     * @return array{deleted:int, bytes:int, failed:array<string,string>}
     */
    public function delete(array $basenames, ?int $adminId = null): array
    {
        return $this->apply('delete', $basenames, $adminId, function (string $path, string $dir): array {
            $verdict = $this->protectedPath->evaluate($dir, $path);
            if ($verdict !== null) {
                return [false, 0, $verdict];
            }

            $size = (int) @filesize($path);
            @unlink($path);
            clearstatcache(true, $path);

            // Verify, never assume.
            return file_exists($path) ? [false, 0, 'could_not_delete'] : [true, $size, ''];
        });
    }

    /**
     * Empty the chosen files in place (the file stays; a logger holding it open keeps working).
     *
     * @param  list<string>  $basenames
     * @return array{deleted:int, bytes:int, failed:array<string,string>}
     */
    public function truncate(array $basenames, ?int $adminId = null): array
    {
        return $this->apply('truncate', $basenames, $adminId, function (string $path, string $dir): array {
            $verdict = $this->protectedPath->evaluateTruncate($dir, $path);
            if ($verdict !== null) {
                return [false, 0, $verdict];
            }

            $size = (int) @filesize($path);
            $handle = @fopen($path, 'r+b');
            if ($handle === false) {
                return [false, 0, 'could_not_open'];
            }
            $ok = ftruncate($handle, 0);
            fclose($handle);
            clearstatcache(true, $path);

            return $ok && (int) @filesize($path) === 0 ? [true, $size, ''] : [false, 0, 'could_not_truncate'];
        });
    }

    /**
     * @param  list<string>  $basenames
     * @param  callable(string, string):array{0:bool,1:int,2:string}  $operation
     * @return array{deleted:int, bytes:int, failed:array<string,string>}
     */
    private function apply(string $action, array $basenames, ?int $adminId, callable $operation): array
    {
        $dir = $this->directory();
        $done = 0;
        $bytes = 0;
        $failed = [];
        $names = [];

        foreach (array_values(array_unique($basenames)) as $name) {
            $path = $this->protectedPath->resolve($dir, (string) $name);

            if ($path === null || ! str_ends_with((string) $name, '.log')) {
                $failed[(string) $name] = 'not_a_log_file';

                continue;
            }

            try {
                [$ok, $size, $reason] = $operation($path, $dir);
            } catch (Throwable $e) {
                Log::channel('cleanup')->warning("Could not {$action} log file {$name}: ".$e->getMessage());
                $ok = false;
                $size = 0;
                $reason = 'error: '.$e->getMessage();
            }

            if ($ok) {
                $done++;
                $bytes += $size;
                $names[] = $name;
            } else {
                $failed[(string) $name] = $reason;
            }
        }

        $this->audit->record('file_'.$action, null, [
            'count' => $done,
            'bytes' => $bytes,
            'files' => array_slice($names, 0, 50),
            'failed' => $failed === [] ? null : $failed,
        ], $adminId);

        return ['deleted' => $done, 'bytes' => $bytes, 'failed' => $failed];
    }
}
