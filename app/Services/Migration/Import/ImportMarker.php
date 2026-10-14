<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\MigrationPaths;
use App\Support\AtomicJsonFile;

/**
 * The one file whose presence means "an import is replacing this site". Everything that must stand aside reads it:
 * the 503 gate in front of the application, the queue guard that holds the workers, the schedule gate, the busy
 * check of the cleanup engine and the janitors that must not reclaim the run's lock.
 *
 * Asking is a single is_file(): no database, no cache, no settings, because the database is the thing being
 * replaced and the cache prefix is the thing being changed. Writing it is atomic.
 */
final class ImportMarker
{
    public function __construct(private readonly ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? MigrationPaths::importMarker();
    }

    public function isActive(): bool
    {
        return is_file($this->path());
    }

    public function write(string $runId): void
    {
        MigrationPaths::ensureDirectory(dirname($this->path()));
        (new AtomicJsonFile($this->path()))->write(['run_id' => $runId, 'since' => now()->toIso8601String()]);
    }

    /** The run the marker belongs to, or null when there is no marker (or it cannot be read). */
    public function runId(): ?string
    {
        if (! $this->isActive()) {
            return null;
        }

        try {
            $data = (new AtomicJsonFile($this->path()))->read();
        } catch (\RuntimeException) {
            return null;
        }

        $id = (string) ($data['run_id'] ?? '');

        return ImportStateStore::validId($id) ? $id : null;
    }

    public function remove(): void
    {
        foreach ([$this->path(), $this->path().'.prev', $this->path().'.lock'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
