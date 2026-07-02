<?php

namespace App\Services\Updates;

use App\Services\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\Log;

/**
 * UpdateSwapper (Module 21, Chunk 3.3) — the atomic file swap that replaces the
 * live core with the extracted new release.
 *
 * Per core path: `rename(root/D → backup/D)` then `rename(staging/D → root/D)`.
 * rename() is atomic per-directory on one filesystem, so the "mixed" window is a
 * few millisecond renames (done inside maintenance mode). Every rename is recorded
 * in `last-swap.json` (state_path) BEFORE the next one, so if the process dies the
 * Recovery Console can finish reversing from that map (rule #47). A mid-swap
 * failure reverses the completed renames in place.
 *
 * PRESERVE paths (.env, storage/) are never in core_paths, so they're untouched.
 * After a successful swap it resets OPcache + realpath cache — but migrations run
 * on a FRESH request (Chunk 3.4), because opcache still holds the OLD classes in
 * the swapping request (rule #46). Symlink-release deployments are blocked by
 * pre-flight (Chunk 3.1), so only the dir-rename path is implemented in V1.
 */
class UpdateSwapper
{
    public function stateFile(): string
    {
        return $this->stateDir().'/last-swap.json';
    }

    /**
     * Swap the extracted staging release into the live root.
     *
     * @return array the swap map (also persisted to last-swap.json)
     */
    public function swap(string $stagingDir, string $version): array
    {
        if (! is_dir($stagingDir)) {
            throw new UpdateException('Staging directory not found: '.$stagingDir);
        }

        $root      = $this->root();
        $backupDir = $this->swapBackupDir($version);
        $this->rrmdir($backupDir);
        $this->ensureDir($backupDir);

        $corePaths = array_values(array_filter(
            (array) config('updates.core_paths', []),
            fn ($rel) => file_exists($stagingDir.DIRECTORY_SEPARATOR.$rel)
        ));

        if ($corePaths === []) {
            throw new UpdateException('Staging release contains none of the core paths — refusing to swap.');
        }

        $map = [
            'version'     => $version,
            'started_at'  => now()->toIso8601String(),
            'root'        => $root,
            'backup_dir'  => $backupDir,
            'staging_dir' => $stagingDir,
            'swapped'     => [],
            'completed'   => false,
        ];
        $this->writeState($map); // persist early so recovery can act if we die

        $rel = null;
        try {
            foreach ($corePaths as $rel) {
                $rootPath    = $root.DIRECTORY_SEPARATOR.$rel;
                $stagingPath = $stagingDir.DIRECTORY_SEPARATOR.$rel;
                $backupPath  = $backupDir.DIRECTORY_SEPARATOR.$rel;
                $hadOriginal = file_exists($rootPath);

                if ($hadOriginal) {
                    $this->ensureDir(dirname($backupPath));
                    $this->rename($rootPath, $backupPath);
                }

                $this->ensureDir(dirname($rootPath));
                $this->rename($stagingPath, $rootPath);

                $map['swapped'][] = ['path' => $rel, 'had_original' => $hadOriginal];
                $this->writeState($map);
            }
        } catch (\Throwable $e) {
            $this->reverse($map['swapped'], $root, $backupDir, $stagingDir);
            $this->clearState();
            throw new UpdateException('File swap failed at ['.$rel.']: '.$e->getMessage().' — changes reversed.');
        }

        $map['completed']  = true;
        $map['swapped_at'] = now()->toIso8601String();
        $this->writeState($map);

        $this->resetRuntimeCaches();

        return $map;
    }

    /**
     * Reverse a swap (used by the failure matrix and the Recovery Console):
     * move the new code back to staging and restore each original.
     */
    public function rollback(?array $map = null): void
    {
        $map ??= $this->readState();
        if (! $map || empty($map['swapped'])) {
            $this->clearState();

            return;
        }

        $this->reverse($map['swapped'], $map['root'], $map['backup_dir'], $map['staging_dir']);
        $this->resetRuntimeCaches();
        $this->clearState();
    }

    /** @param array<int,array{path:string,had_original:bool}> $swapped */
    private function reverse(array $swapped, string $root, string $backupDir, string $stagingDir): void
    {
        foreach (array_reverse($swapped) as $entry) {
            $rel         = $entry['path'];
            $rootPath    = $root.DIRECTORY_SEPARATOR.$rel;
            $backupPath  = $backupDir.DIRECTORY_SEPARATOR.$rel;
            $stagingPath = $stagingDir.DIRECTORY_SEPARATOR.$rel;

            // Move the new code back out of the way (preserve it in staging).
            if (file_exists($rootPath)) {
                $this->ensureDir(dirname($stagingPath));
                @$this->renameQuiet($rootPath, $stagingPath);
            }

            // Restore the original (a path that had no original stays removed).
            if (! empty($entry['had_original']) && file_exists($backupPath)) {
                $this->ensureDir(dirname($rootPath));
                $this->renameQuiet($backupPath, $rootPath);
            }
        }
    }

    /* ---- Runtime caches ------------------------------------------------ */

    public function resetRuntimeCaches(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        clearstatcache(true);
    }

    /* ---- State file ---------------------------------------------------- */

    public function readState(): ?array
    {
        $file = $this->stateFile();
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    private function writeState(array $map): void
    {
        $this->ensureDir($this->stateDir());
        file_put_contents($this->stateFile(), json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function clearState(): void
    {
        if (is_file($this->stateFile())) {
            @unlink($this->stateFile());
        }
    }

    /* ---- Helpers ------------------------------------------------------- */

    private function rename(string $from, string $to): void
    {
        if (! @rename($from, $to)) {
            throw new UpdateException('Could not move '.$from.' → '.$to);
        }
    }

    private function renameQuiet(string $from, string $to): void
    {
        if (! @rename($from, $to)) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->error('Rollback rename failed: '.$from.' → '.$to);
        }
    }

    private function root(): string
    {
        return rtrim((string) (config('updates.root_path') ?: base_path()), '/\\');
    }

    private function swapBackupDir(string $version): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $version);

        return $this->stateDir().'/swap-backup/'.$safe;
    }

    private function stateDir(): string
    {
        return rtrim((string) config('updates.state_path', storage_path('app/updates')), '/\\');
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
