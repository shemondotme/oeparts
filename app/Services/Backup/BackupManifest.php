<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Storage;

/**
 * BackupManifest (Module 14/21, Chunk 2.1) — builds and reads the per-run
 * manifest: the self-describing index of every part that makes up a backup.
 *
 * The manifest is what a restore (Chunk 2.5) and the app-independent Recovery
 * Console read to reassemble a backup — it must be complete and readable
 * WITHOUT the app that produced it, so it is stored as plain JSON alongside the
 * parts. It is written on finalize, after part totals are known.
 */
class BackupManifest
{
    public const SCHEMA_VERSION = 1;

    /** Disk-relative path where a run's manifest lives. */
    public function pathFor(BackupRun $run): string
    {
        return 'backups/'.$run->getKey().'/manifest.json';
    }

    /**
     * Build the manifest array for a completed run (parts already persisted).
     */
    public function build(BackupRun $run): array
    {
        $run->loadMissing('parts');

        return [
            'schema'      => self::SCHEMA_VERSION,
            'run_id'      => (int) $run->getKey(),
            'profile'     => $run->profile,
            'trigger'     => $run->trigger,
            'app_version' => $run->app_version,
            'php_version' => $run->php_version,
            'db_version'  => $run->db_version,
            'disk'        => $run->disk,
            'encrypted'   => (bool) $run->encrypted,
            'cipher'      => (string) config('backup.encryption.cipher', 'aes-256-gcm'),
            'compression' => (string) config('backup.compression', 'gzip'),
            'created_at'  => optional($run->started_at)->toIso8601String(),
            'part_count'  => (int) $run->part_count,
            'total_bytes' => (int) $run->total_bytes,
            'parts'       => $run->parts
                ->sortBy([['type', 'asc'], ['sequence', 'asc']])
                ->map(fn ($p) => [
                    'type'     => $p->type,
                    'sequence' => (int) $p->sequence,
                    'name'     => $p->name,
                    'disk'     => $p->disk,
                    'path'     => $p->path,
                    'sha256'   => $p->sha256,
                    'bytes'    => (int) $p->bytes,
                    'rows'     => $p->rows,
                    'meta'     => $p->meta,
                ])
                ->values()
                ->all(),
        ];
    }

    /** Serialise + persist the manifest; returns the disk-relative path written. */
    public function write(BackupRun $run): string
    {
        $path = $this->pathFor($run);

        Storage::disk($run->disk)->put($path, $this->json($run));

        return $path;
    }

    /** Canonical JSON string of the manifest (also the input to the checksum). */
    public function json(BackupRun $run): string
    {
        return (string) json_encode($this->build($run), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** SHA-256 over the manifest JSON — a single fingerprint for the whole run. */
    public function checksum(BackupRun $run): string
    {
        return hash('sha256', $this->json($run));
    }

    /** Read a manifest back from disk (null if missing/corrupt). */
    public function read(BackupRun $run): ?array
    {
        if (! $run->manifest_path || ! Storage::disk($run->disk)->exists($run->manifest_path)) {
            return null;
        }

        $data = json_decode((string) Storage::disk($run->disk)->get($run->manifest_path), true);

        return is_array($data) ? $data : null;
    }
}
