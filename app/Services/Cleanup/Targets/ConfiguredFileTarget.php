<?php

namespace App\Services\Cleanup\Targets;

use App\Models\BackupRun;
use App\Models\ProductImportRun;
use App\Models\UpdateHistory;
use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\ProtectedPath;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * A file/folder target described by an array in config('cleanup.targets') (entries with `'type' => 'file'`).
 *
 * Definition keys:
 *   key, label, description, group     identity / where it is listed ('files' | 'backups')
 *   root                               a NAMED registered root (see rootPaths()): never a free-form path
 *   pattern                            regex the basename must match
 *   recursive, dirs                    walk below the root / treat matching folders as one unit
 *   days, floor                        default retention (age) and the shortest an operator may set
 *   auto                               included in the scheduled run by default
 *   lock                               also holds the shared backup/update lock while it runs
 *   rule                               a NAMED extra rule (see eligible())
 *   action                             'delete' (default) or 'truncate' (empty an oversized log in place)
 *
 * Roots and rules are looked up by name here, so a definition can never point the engine at an arbitrary path.
 */
class ConfiguredFileTarget extends FileSweepTarget
{
    /** Logs bigger than this are emptied in place (the file stays; an open handle would keep a deleted file's space). */
    public const OVERSIZED_LOG_BYTES = 50 * 1024 * 1024;

    /** @var array<string, mixed> */
    private array $def;

    /** @var list<string>|null */
    private ?array $activeUpdatePaths = null;

    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(array $definition, ?ProtectedPath $protectedPath = null)
    {
        foreach (['key', 'label', 'root', 'pattern', 'days'] as $required) {
            if (! isset($definition[$required])) {
                throw new InvalidArgumentException("Cleanup file target definition is missing '{$required}'.");
            }
        }
        if (! in_array($definition['action'] ?? 'delete', ['delete', 'truncate'], true)) {
            throw new InvalidArgumentException("Unknown cleanup file action '{$definition['action']}'.");
        }

        $this->def = $definition;
        $this->rootPaths(); // fail fast on an unknown root name
        parent::__construct($protectedPath);
    }

    public function key(): string
    {
        return (string) $this->def['key'];
    }

    public function label(): string
    {
        return (string) $this->def['label'];
    }

    public function description(): string
    {
        return (string) ($this->def['description'] ?? '');
    }

    public function group(): string
    {
        return (string) ($this->def['group'] ?? 'files');
    }

    public function defaultDays(): int
    {
        return (int) $this->def['days'];
    }

    public function floorDays(): int
    {
        return (int) ($this->def['floor'] ?? 0);
    }

    public function autoByDefault(): bool
    {
        return (bool) ($this->def['auto'] ?? true);
    }

    public function needsBackupLock(): bool
    {
        return (bool) ($this->def['lock'] ?? false);
    }

    protected function roots(): array
    {
        return $this->rootPaths();
    }

    protected function recursive(): bool
    {
        return (bool) ($this->def['recursive'] ?? false);
    }

    protected function allowDirs(): bool
    {
        return (bool) ($this->def['dirs'] ?? false);
    }

    protected function matches(string $basename): bool
    {
        return preg_match((string) $this->def['pattern'], $basename) === 1;
    }

    private function truncates(): bool
    {
        return ($this->def['action'] ?? 'delete') === 'truncate';
    }

    /* ---- named roots ---------------------------------------------------- */

    /** @return list<string> */
    private function rootPaths(): array
    {
        $stateDir = rtrim((string) config('updates.state_path', storage_path('app/updates')), '/\\');

        return match ($this->def['root']) {
            'logs' => [rtrim((string) config('cleanup.log_dir', storage_path('logs')), '/'.chr(92))],
            'update_downloads' => [$stateDir.DIRECTORY_SEPARATOR.'downloads'],
            'update_staging' => [$stateDir.DIRECTORY_SEPARATOR.'staging'],
            'update_state' => [$stateDir],
            'restore' => [rtrim((string) config('cleanup.restore_dir', storage_path('app/restore')), '/'.chr(92))],
            // Where backup runs write: the staging disk always, the destination disk too when it is local and different.
            'backups' => $this->diskDirs([(string) config('backup.staging_disk', 'local'), (string) config('backup.disk', 'local')], 'backups'),
            'imports' => $this->diskDirs([(string) config('imports.disk', 'local')], (string) config('imports.path', 'imports')),
            'livewire_tmp' => $this->diskDirs(['local'], 'livewire-tmp'),
            'cleanup_archives' => $this->diskDirs(['local'], 'cleanup-archives'),
            'cleanup_work' => [storage_path('framework/cleanup')],
            'e2e_scratch' => $this->diskDirs(['local'], 'e2e-cleanup'), // test-only target, see config/cleanup.php
            // File-backed stores only: with Redis/database drivers there is nothing on disk to sweep.
            'file_cache' => config('cache.stores.'.config('cache.default').'.driver') === 'file'
                ? [rtrim((string) config('cache.stores.file.path', storage_path('framework/cache/data')), '/\\')] : [],
            'file_sessions' => config('session.driver') === 'file'
                ? [rtrim((string) config('session.files', storage_path('framework/sessions')), '/\\')] : [],
            default => throw new InvalidArgumentException("Unknown cleanup root '{$this->def['root']}' on target '{$this->key()}'."),
        };
    }

    /**
     * @param  list<string>  $disks
     * @return list<string>
     */
    private function diskDirs(array $disks, string $directory): array
    {
        $paths = [];

        foreach (array_unique($disks) as $disk) {
            // Only local-driver disks have a directory inside the project; a remote disk has nothing to sweep here.
            if (config("filesystems.disks.{$disk}.driver") === 'local') {
                $paths[] = rtrim(Storage::disk($disk)->path($directory), '/\\');
            }
        }

        return array_values(array_unique($paths));
    }

    /* ---- age ------------------------------------------------------------ */

    protected function minAgeSeconds(CleanupContext $ctx, int $now): int
    {
        $age = parent::minAgeSeconds($ctx, $now);

        // A file session is still valid for `lifetime` minutes after its LAST activity (mtime): never touch one
        // inside that window, with a margin, however short the configured retention is.
        if (($this->def['rule'] ?? null) === 'session_expired') {
            $age = max($age, ((int) config('session.lifetime', 120) * 60) + 600);
        }

        return $age;
    }

    /* ---- named rules ---------------------------------------------------- */

    protected function eligible(string $absPath, string $basename, int $now): bool
    {
        if ($this->truncates()) {
            return @filesize($absPath) !== false && filesize($absPath) >= self::OVERSIZED_LOG_BYTES;
        }

        return match ($this->def['rule'] ?? null) {
            null, 'session_expired' => true,
            'expired_cache' => $this->cacheEntryExpired($absPath, $now),
            'no_active_update' => ! in_array($absPath, $this->activeUpdatePaths(), true),
            'run_terminal_or_gone' => $this->backupRunIsFinishedOrGone($basename, '/^advance-run-(\d+)\.lock$/'),
            'restore_dir' => $this->backupRunIsFinishedOrGone($basename, '/^run-(\d+)$/'),
            'orphan_backup_dir' => $this->isOrphanBackupDir($basename),
            'unreferenced_import' => ! ProductImportRun::query()
                ->where('status', ProductImportRun::STATUS_RUNNING)
                ->where('path', 'like', '%'.$basename)
                ->exists(),
            default => throw new InvalidArgumentException("Unknown cleanup rule '{$this->def['rule']}' on target '{$this->key()}'."),
        };
    }

    /** The cache file driver stores the expiry as the first 10 characters of every file. */
    private function cacheEntryExpired(string $path, int $now): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $head = (string) fread($handle, 10);
        fclose($handle);

        return strlen($head) === 10 && ctype_digit($head) && (int) $head < $now;
    }

    /** Paths an update that has not finished (and not been abandoned) still needs. */
    private function activeUpdatePaths(): array
    {
        if ($this->activeUpdatePaths === null) {
            $paths = [];

            $rows = UpdateHistory::query()
                ->whereNotIn('status', [UpdateHistory::STATUS_SUCCESS, UpdateHistory::STATUS_FAILED, UpdateHistory::STATUS_ROLLED_BACK])
                ->where('updated_at', '>=', now()->subSeconds((int) config('cleanup.busy_row_fresh_seconds', 6 * 3600)))
                ->get(['meta']);

            foreach ($rows as $row) {
                foreach (['download_path', 'staging_dir'] as $key) {
                    if (! empty($row->meta[$key])) {
                        $paths[] = (string) $row->meta[$key];
                    }
                }
            }

            $this->activeUpdatePaths = $paths;
        }

        return $this->activeUpdatePaths;
    }

    private function backupRunIsFinishedOrGone(string $basename, string $pattern): bool
    {
        if (preg_match($pattern, $basename, $m) !== 1) {
            return false;
        }

        $run = BackupRun::query()->find((int) $m[1]);

        return $run === null || $run->isTerminal();
    }

    /** A backups/{id} folder is an orphan when no live run owns it: no row, or a failed/pruned/cleaned one. */
    private function isOrphanBackupDir(string $basename): bool
    {
        if (! ctype_digit($basename)) {
            return false;
        }

        $run = BackupRun::query()->find((int) $basename);

        if ($run === null) {
            return true;
        }

        if (in_array($run->status, [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING], true)) {
            return false;
        }

        $meta = $run->meta ?? [];

        // A successful backup whose files are still meant to exist is NEVER an orphan.
        return $run->status === BackupRun::STATUS_FAILED || isset($meta['pruned_at']) || isset($meta['cleaned_at']);
    }

    /* ---- truncating oversized logs -------------------------------------- */

    /** A truncated log stays on disk; it is done once it is no longer oversized. */
    protected function stillPending(string $path): bool
    {
        return $this->truncates()
            ? (is_file($path) && (int) @filesize($path) >= self::OVERSIZED_LOG_BYTES)
            : parent::stillPending($path);
    }

    protected function evaluateEntry(ProtectedPath $protected, string $root, string $path, int $minAge, int $now): ?string
    {
        return $this->truncates()
            ? $protected->evaluateTruncate($root, $path)
            : parent::evaluateEntry($protected, $root, $path, $minAge, $now);
    }

    protected function remove(string $path, ProtectedPath $protected): array
    {
        if (! $this->truncates()) {
            return parent::remove($path, $protected);
        }

        clearstatcache(true, $path);
        $size = (int) @filesize($path);

        // Opening with 'r+' and truncating keeps the same inode, so a logger holding the file open
        // (it appends) simply continues writing at offset 0. Deleting it instead would free nothing.
        $handle = @fopen($path, 'r+b');
        if ($handle === false) {
            return [false, 0, 0];
        }
        $ok = ftruncate($handle, 0);
        fclose($handle);
        clearstatcache(true, $path);

        return $ok && (int) @filesize($path) === 0 ? [true, $size, $size] : [false, 0, 0];
    }
}
