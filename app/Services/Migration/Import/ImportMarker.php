<?php

namespace App\Services\Migration\Import;

use App\Services\Instance\BlockLevel;
use App\Services\Instance\Contracts\BlockSource;
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
final class ImportMarker implements BlockSource
{
    public function __construct(private readonly ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? MigrationPaths::importMarker();
    }

    public function isActive(): bool
    {
        // PHP remembers a successful stat of a file for the life of the process. A queue worker or the scheduler lives for
        // days and the marker is removed by ANOTHER process, so without this a worker would stay held for a marker that is gone.
        clearstatcache(true, $this->path());

        return is_file($this->path());
    }

    /** As a block source of the SystemGate: while the marker exists only the commands that keep the installation healthy run. */
    public function level(): BlockLevel
    {
        return $this->isActive() ? BlockLevel::Everything : BlockLevel::None;
    }

    public function reason(): string
    {
        return 'a site import is replacing this installation';
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
