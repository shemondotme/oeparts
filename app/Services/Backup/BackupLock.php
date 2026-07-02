<?php

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\BackupLockException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * BackupLock (Module 14/21, Chunk 2.1) — the single mutual-exclusion lock shared
 * by the Backup Engine and the Update Engine. Only one backup OR update may run
 * at a time on an instance (they touch the same files/DB), so both coordinate
 * through one lock file at config('updates.state_path')/lock.
 *
 * Pure filesystem, no queue/Redis dependency (rule #41 — shared-hosting-safe).
 * Acquisition is atomic via O_EXCL (`fopen(..., 'x')`): the create fails if the
 * file already exists, so two concurrent requests can never both win.
 */
class BackupLock
{
    public function path(): string
    {
        $dir = (string) config('updates.state_path', storage_path('app/updates'));

        return rtrim($dir, "/\\").DIRECTORY_SEPARATOR.'lock';
    }

    /**
     * Acquire the lock for $owner (e.g. "backup:12" or "update:1.1.0").
     *
     * @throws BackupLockException if already held
     */
    public function acquire(string $owner): void
    {
        $path = $this->path();
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $handle = @fopen($path, 'x'); // O_EXCL — fails if the lock already exists

        if ($handle === false) {
            $held = $this->owner();

            throw new BackupLockException(
                'A backup or update is already in progress'
                .($held['owner'] ?? null ? ' (held by '.$held['owner'].')' : '').'.'
            );
        }

        fwrite($handle, (string) json_encode([
            'owner'       => $owner,
            'acquired_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
        fclose($handle);
    }

    public function isLocked(): bool
    {
        return is_file($this->path());
    }

    /** @return array{owner?:string,acquired_at?:string} */
    public function owner(): array
    {
        if (! is_file($this->path())) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($this->path()), true);

        return is_array($data) ? $data : [];
    }

    public function release(): void
    {
        if (is_file($this->path())) {
            @unlink($this->path());
        }
    }

    /** True when the lock is older than $maxAgeSeconds (a crashed, abandoned run). */
    public function isStale(int $maxAgeSeconds): bool
    {
        $owner = $this->owner();

        if (empty($owner['acquired_at'])) {
            // Locked but unreadable/undated — treat any such file as stale.
            return $this->isLocked();
        }

        try {
            return Carbon::parse($owner['acquired_at'])->addSeconds($maxAgeSeconds)->isPast();
        } catch (\Throwable $e) {
            Log::channel(config('updates.log_channel', 'stack'))
                ->warning('Backup lock timestamp unparseable: '.$e->getMessage());

            return true;
        }
    }
}
