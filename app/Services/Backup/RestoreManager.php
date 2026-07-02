<?php

namespace App\Services\Backup;

use App\Models\BackupPart;
use App\Models\BackupRun;
use App\Services\Backup\Exceptions\RestoreException;
use App\Services\Updates\UpdateChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RestoreManager (Module 14/21, Chunk 2.5) — reassembles, verifies, decrypts and
 * applies a backup produced by the Backup Engine (2.2–2.4).
 *
 * Restore always reads backup_parts rows for the run. For CROSS-SERVER restore
 * (a fresh box has the backup files + the unencrypted manifest.json TOC but no
 * DB rows), {@see importManifest()} recreates the run + part rows from the TOC
 * first — that's exactly why the TOC is self-describing and unencrypted.
 *
 * Verification is layered: each part's ciphertext sha256 is checked before
 * decryption, its plaintext sha256 after; every restored file is re-hashed
 * against the file manifest. DB parts are applied with FK checks disabled and in
 * creation order (schema then data, per table). Partial restores (DB-only,
 * files-only, single-table) are supported via {@see RestoreOptions}.
 */
class RestoreManager
{
    /** runId:vol → decrypted local volume temp path (reused across a file loop). */
    private array $volumeCache = [];

    public function __construct(private readonly BackupCipher $cipher) {}

    /* ---- Cross-server import ------------------------------------------- */

    /** Recreate a BackupRun + its parts from an unencrypted manifest.json TOC. */
    public function importManifest(string $disk, string $manifestPath): BackupRun
    {
        if (! Storage::disk($disk)->exists($manifestPath)) {
            throw new RestoreException('Manifest not found: ['.$disk.'] '.$manifestPath);
        }

        $toc = json_decode((string) Storage::disk($disk)->get($manifestPath), true);
        if (! is_array($toc) || empty($toc['parts'])) {
            throw new RestoreException('Manifest is missing or malformed: '.$manifestPath);
        }

        $run = BackupRun::create([
            'profile'       => $toc['profile'] ?? BackupRun::PROFILE_FULL,
            'status'        => BackupRun::STATUS_SUCCESS,
            'trigger'       => BackupRun::TRIGGER_MANUAL,
            'disk'          => $disk,
            'app_version'   => $toc['app_version'] ?? null,
            'php_version'   => $toc['php_version'] ?? null,
            'db_version'    => $toc['db_version'] ?? null,
            'part_count'    => (int) ($toc['part_count'] ?? count($toc['parts'])),
            'total_bytes'   => (int) ($toc['total_bytes'] ?? 0),
            'manifest_path' => $manifestPath,
            'meta'          => ['imported' => true],
        ]);

        foreach ($toc['parts'] as $p) {
            $run->parts()->create([
                'type'     => $p['type'] ?? 'other',
                'sequence' => (int) ($p['sequence'] ?? 0),
                'name'     => $p['name'] ?? null,
                'disk'     => $p['disk'] ?? $disk,
                'path'     => (string) ($p['path'] ?? ''),
                'sha256'   => $p['sha256'] ?? null,
                'bytes'    => (int) ($p['bytes'] ?? 0),
                'rows'     => $p['rows'] ?? null,
                'meta'     => $p['meta'] ?? null,
            ]);
        }

        return $run;
    }

    /* ---- Orchestration ------------------------------------------------- */

    public function restore(BackupRun $run, ?RestoreOptions $options = null): RestoreReport
    {
        $options ??= new RestoreOptions;
        $report = new RestoreReport;

        $report->warnings = array_merge(
            $report->warnings,
            $this->validateVersion($run, $options->strictVersion)
        );

        try {
            if ($options->database || $options->table !== null) {
                $this->restoreDatabase($run, $options, $report);
            }

            if ($options->files && $options->table === null) {
                $target = $options->targetRoot ?? storage_path('app/restore/run-'.$run->getKey());
                $this->restoreFiles($run, $target, $report);
            }
        } finally {
            $this->clearVolumeCache();
        }

        return $report;
    }

    /** Backup app version vs this server. Newer-onto-older is risky (cross-server). */
    public function validateVersion(BackupRun $run, bool $strict): array
    {
        $backup  = (string) $run->app_version;
        $current = app(UpdateChecker::class)->currentVersion();

        if ($backup !== '' && $current !== 'unknown' && version_compare($backup, $current, '>')) {
            $msg = 'Backup was taken on app v'.$backup.' but this server runs v'.$current
                .' — restoring a newer backup onto older code may fail.';

            if ($strict) {
                throw new RestoreException($msg);
            }

            return [$msg];
        }

        return [];
    }

    /* ---- Database restore ---------------------------------------------- */

    private function restoreDatabase(BackupRun $run, RestoreOptions $options, RestoreReport $report): void
    {
        $parts = $run->parts()
            ->where('type', BackupPart::TYPE_DB)
            ->when($options->table !== null, fn ($q) => $q->where('name', $options->table))
            ->orderBy('id') // creation order = schema before data, per table
            ->get();

        if ($parts->isEmpty()) {
            $report->warnings[] = $options->table !== null
                ? 'No backup data found for table ['.$options->table.'].'
                : 'Backup contains no database parts.';

            return;
        }

        $this->toggleForeignKeys(false);

        try {
            foreach ($parts as $part) {
                $sql = (string) gzdecode($this->plaintextOf($part));
                DB::unprepared($sql);
                $report->statementsRun++;

                if (($part->meta['kind'] ?? null) === 'schema' && ! in_array($part->name, $report->tablesRestored, true)) {
                    $report->tablesRestored[] = $part->name;
                }
            }
        } finally {
            $this->toggleForeignKeys(true);
        }
    }

    private function toggleForeignKeys(bool $on): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared('SET FOREIGN_KEY_CHECKS='.($on ? '1' : '0').';');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('PRAGMA foreign_keys = '.($on ? 'ON' : 'OFF').';');
        }
    }

    /* ---- File restore -------------------------------------------------- */

    private function restoreFiles(BackupRun $run, string $targetRoot, RestoreReport $report): void
    {
        $manifestPart = $run->parts()
            ->where('type', BackupPart::TYPE_FILES)
            ->where('name', 'files-manifest')
            ->first();

        if (! $manifestPart) {
            return; // e.g. an update_safety backup has no files
        }

        $fileManifest = json_decode((string) gzdecode($this->plaintextOf($manifestPart)), true);
        $targetRoot   = rtrim($targetRoot, "/\\");

        foreach ((array) ($fileManifest['files'] ?? []) as $entry) {
            if (! empty($entry['deleted']) || empty($entry['path'])) {
                continue;
            }

            $target = $targetRoot.'/'.$entry['path'];
            $dir    = dirname($target);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            if ((int) ($entry['size'] ?? 0) === 0 || empty($entry['segments'])) {
                file_put_contents($target, '');
            } else {
                $this->writeFileFromVolume($run, $entry, $target);
            }

            $report->filesRestored++;

            if (! empty($entry['sha256']) && hash_file('sha256', $target) === $entry['sha256']) {
                $report->filesVerified++;
            } elseif (! empty($entry['sha256'])) {
                $report->errors[] = 'File failed verification after restore: '.$entry['path'];
            }
        }
    }

    /** Reassemble one file from its volume's gzip segments. */
    private function writeFileFromVolume(BackupRun $run, array $entry, string $target): void
    {
        $sourceRun = (int) ($entry['source_run'] ?? $run->getKey());
        $volume    = $this->decryptedVolume($sourceRun, (int) $entry['vol']);

        $in  = fopen($volume, 'rb');
        $out = fopen($target, 'wb');

        try {
            foreach ($entry['segments'] as [$offset, $clen, $rawLen]) {
                fseek($in, (int) $offset);
                $compressed = fread($in, (int) $clen);
                fwrite($out, (string) gzdecode($compressed));
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** Decrypt a volume to a local temp file once, reusing it across files. */
    private function decryptedVolume(int $runId, int $vol): string
    {
        $key = $runId.':'.$vol;
        if (isset($this->volumeCache[$key])) {
            return $this->volumeCache[$key];
        }

        $part = BackupPart::query()
            ->where('backup_run_id', $runId)
            ->where('name', 'vol-'.$vol)
            ->first();

        if (! $part) {
            throw new RestoreException('Missing volume '.$vol.' for backup run '.$runId
                .' (an incremental baseline may not be present on this server).');
        }

        $dest = $this->tempPath('vol-'.$runId.'-'.$vol.'.oevol');
        $this->decryptPartToFile($part, $dest);

        return $this->volumeCache[$key] = $dest;
    }

    /* ---- Decrypt + verify helpers -------------------------------------- */

    /** Load a small part (db/manifest) into memory, verified + decrypted (still gz). */
    private function plaintextOf(BackupPart $part): string
    {
        $enc = (string) Storage::disk($part->disk)->get($part->path);
        $this->verifyCiphertext($part, $enc);

        $plain = ($part->meta['encrypted'] ?? false) === true
            ? $this->cipher->decryptData($enc)
            : $enc;

        $this->verifyPlaintext($part, $plain);

        return $plain;
    }

    /** Stream a (possibly large, possibly remote) part to a decrypted local file. */
    private function decryptPartToFile(BackupPart $part, string $destAbs): void
    {
        $tmpEnc = $destAbs.'.enc';
        $this->streamToLocal($part, $tmpEnc);

        if (($part->meta['encrypted'] ?? false) === true) {
            $this->cipher->decryptFile($tmpEnc, $destAbs);
        } else {
            @copy($tmpEnc, $destAbs);
        }

        @unlink($tmpEnc);

        if (! empty($part->meta['plain_sha256']) && hash_file('sha256', $destAbs) !== $part->meta['plain_sha256']) {
            throw new RestoreException('Volume '.$part->name.' failed plaintext verification.');
        }
    }

    /** Copy a part off its disk to a local file, verifying the ciphertext sha256. */
    private function streamToLocal(BackupPart $part, string $destAbs): void
    {
        $dir = dirname($destAbs);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $in  = Storage::disk($part->disk)->readStream($part->path);
        $out = fopen($destAbs, 'wb');
        if ($in === false || $out === false) {
            throw new RestoreException('Could not open backup part for restore: '.$part->name);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($part->sha256 && hash_file('sha256', $destAbs) !== $part->sha256) {
            throw new RestoreException('Backup part '.$part->name.' failed integrity check (ciphertext sha256).');
        }
    }

    private function verifyCiphertext(BackupPart $part, string $enc): void
    {
        if ($part->sha256 && hash('sha256', $enc) !== $part->sha256) {
            throw new RestoreException('Backup part '.$part->name.' failed integrity check (ciphertext sha256).');
        }
    }

    private function verifyPlaintext(BackupPart $part, string $plain): void
    {
        if (! empty($part->meta['plain_sha256']) && hash('sha256', $plain) !== $part->meta['plain_sha256']) {
            throw new RestoreException('Backup part '.$part->name.' failed plaintext verification.');
        }
    }

    /* ---- Temp files ---------------------------------------------------- */

    private function tempPath(string $name): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-restore';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.DIRECTORY_SEPARATOR.$name;
    }

    private function clearVolumeCache(): void
    {
        foreach ($this->volumeCache as $path) {
            @unlink($path);
        }
        $this->volumeCache = [];
    }
}
