<?php

namespace App\Services\Backup\Stages;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\BackupCipher;
use App\Services\Backup\Contracts\BackupStage;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Backup\StageStepResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * EncryptTransportStage (Module 14/21, Chunk 2.4) — the final pipeline stage:
 * encrypts every staged part and streams it to its destination.
 *
 * The DB (2.2) and file (2.3) stages write plaintext parts to the LOCAL staging
 * disk (config('backup.staging_disk')). This stage AES-256-GCM-encrypts each
 * staged part (BackupCipher) — batching as many WHOLE parts as fit within
 * backup.encryption.batch_seconds per step (never a partial part; bounded by
 * TIME, not count, so a step still can't run unboundedly — rule #48; most
 * parts are a few KB-MB, so a single large volume can still fill an entire
 * step on its own), then:
 *   - destination == staging (local): drops the plaintext, keeps the `.enc`;
 *   - destination off-site (S3-EU / SFTP): STREAMS the `.enc` up, verifies it,
 *     then deletes BOTH local files (never stage the whole backup off-site in
 *     memory — LOCKED DECISION #5).
 * The backup_parts row is rewritten to point at the encrypted artifact (disk,
 * path, enc sha256/bytes) with the plaintext sha256 preserved in meta for
 * post-restore verification. Idempotent: an already-encrypted part is skipped,
 * so a resumed run never double-encrypts.
 */
class EncryptTransportStage implements BackupStage
{
    /** EU S3 regions — off-site PII must stay in the EU (GDPR, rule #45). */
    private const EU_REGIONS = [
        'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-central-1', 'eu-central-2',
        'eu-north-1', 'eu-south-1', 'eu-south-2',
    ];

    public function __construct(private readonly BackupCipher $cipher) {}

    public function key(): string
    {
        return 'encrypt';
    }

    public function step(BackupRun $run, array $state): StageStepResult
    {
        if (! isset($state['init'])) {
            $state = $this->initialise($run);
        }

        $deadline = microtime(true) + max(0.0, (float) config('backup.encryption.batch_seconds', 5));
        $secured  = 0;
        $lastName = null;

        do {
            $part = $run->parts()
                ->where('id', '>', (int) $state['cursor_id'])
                ->orderBy('id')
                ->first();

            if (! $part) {
                break;
            }

            $this->securePart($run, $part);

            $state['cursor_id'] = (int) $part->getKey();
            $state['processed'] = (int) $state['processed'] + 1;
            $secured++;
            $lastName = $part->name;
        } while (microtime(true) < $deadline);

        $total    = max(1, (int) $state['total_parts']);
        $fraction = min($state['processed'], $total) / $total;

        $remaining = $run->parts()->where('id', '>', (int) $state['cursor_id'])->exists();

        if (! $remaining) {
            return StageStepResult::complete(null, 'encrypt: all parts secured');
        }

        $note = $secured > 1 ? "encrypt: {$secured} parts (through {$lastName})" : "encrypt: {$lastName}";

        return StageStepResult::progress($state, null, $note, $fraction);
    }

    private function initialise(BackupRun $run): array
    {
        if (! $this->cipher->hasKey()) {
            // Mandatory encryption — refuse rather than write plaintext PII (rule #45).
            throw new BackupException(
                'OE_BACKUP_KEY is not set. Backups are mandatorily encrypted (GDPR — customer PII). '
                .'Set OE_BACKUP_KEY in .env (e.g. '.$this->cipher->generateKey().') and store it safely: '
                .'losing this key makes every backup permanently unrecoverable.'
            );
        }

        $this->warnIfNonEuOffsite($run->disk);

        return ['init' => true, 'cursor_id' => 0, 'processed' => 0, 'total_parts' => $run->parts()->count()];
    }

    private function securePart(BackupRun $run, BackupChunk $part): void
    {
        // Idempotent: a resumed run must never re-encrypt an already-secured part.
        if (($part->meta['encrypted'] ?? false) === true) {
            // Crash-safety belt: if a prior attempt died between marking this
            // part encrypted and actually deleting the plaintext source below,
            // the plaintext survives on disk — unacceptable for mandatorily
            // encrypted backups (rule #45, customer PII). Finish the cleanup
            // now; idempotent no-op once it's actually gone.
            $this->deleteLeftoverPlaintext($part);

            return;
        }

        $srcDisk = $part->disk;                 // local staging
        $srcRel  = $part->path;
        $encRel  = $srcRel.'.enc';

        $srcAbs = Storage::disk($srcDisk)->path($srcRel);
        $encAbs = Storage::disk($srcDisk)->path($encRel);

        $meta = $this->cipher->encryptFile($srcAbs, $encAbs);

        $dest = $run->disk;                     // final destination (may be off-site)
        $localEncPath = null;

        if ($dest === $srcDisk) {
            $finalDisk = $srcDisk;
        } else {
            $handle = fopen($encAbs, 'rb');
            Storage::disk($dest)->writeStream($encRel, $handle);
            if (is_resource($handle)) {
                fclose($handle);
            }

            $this->verifyOffsiteUpload($dest, $encRel, (int) $meta['enc_bytes'], (string) $meta['enc_sha256']);

            $finalDisk = $dest;
            $localEncPath = $encRel; // local .enc copy still needs cleanup below
        }

        // Mark this part encrypted — and record exactly where the plaintext
        // (and, off-site, the local .enc copy) still lives — BEFORE deleting
        // anything. A crash between this save and the deletes below must
        // resume straight into the "already encrypted" branch above and
        // finish the cleanup, never re-attempt encryptFile() against a
        // source that might already be gone.
        $part->update([
            'disk'   => $finalDisk,
            'path'   => $encRel,
            'bytes'  => $meta['enc_bytes'],
            'sha256' => $meta['enc_sha256'],
            'meta'   => array_merge((array) ($part->meta ?? []), [
                'encrypted'    => true,
                'cipher'       => $meta['cipher'],
                'frames'       => $meta['frames'],
                'plain_sha256' => $meta['plain_sha256'],
                'plain_bytes'  => $meta['plain_bytes'],
                'plain_disk'   => $srcDisk,
                'plain_path'   => $srcRel,
                'plain_enc_local_path' => $localEncPath,
            ]),
        ]);

        $this->deleteLeftoverPlaintext($part->fresh());
    }

    /**
     * Delete the plaintext source and (for off-site parts) the local .enc
     * copy, if still present. Safe to call repeatedly — every check is
     * exists()-guarded, so a resume after a crash mid-cleanup just finishes
     * whatever deletion didn't happen yet.
     */
    private function deleteLeftoverPlaintext(BackupChunk $part): void
    {
        $meta = (array) ($part->meta ?? []);
        $plainDisk = $meta['plain_disk'] ?? null;
        $plainPath = $meta['plain_path'] ?? null;

        if ($plainDisk && $plainPath && Storage::disk($plainDisk)->exists($plainPath)) {
            Storage::disk($plainDisk)->delete($plainPath);
        }

        $localEncPath = $meta['plain_enc_local_path'] ?? null;
        if ($plainDisk && $localEncPath && Storage::disk($plainDisk)->exists($localEncPath)) {
            Storage::disk($plainDisk)->delete($localEncPath);
        }
    }

    /**
     * Verify an off-site upload before the caller trusts it enough to delete
     * the only other copies (local plaintext + local .enc). Existence alone
     * (the previous check) is satisfied by a truncated/corrupt object from a
     * dropped connection or partial multipart upload — this additionally
     * confirms the remote byte count and a full streaming SHA-256 match the
     * artifact actually encrypted locally.
     */
    private function verifyOffsiteUpload(string $dest, string $encRel, int $expectedBytes, string $expectedSha256): void
    {
        if (! Storage::disk($dest)->exists($encRel)) {
            throw new BackupException('Off-site upload of '.$encRel.' to ['.$dest.'] could not be verified: object not found.');
        }

        $actualBytes = (int) Storage::disk($dest)->size($encRel);
        if ($actualBytes !== $expectedBytes) {
            throw new BackupException(
                'Off-site upload of '.$encRel.' to ['.$dest.'] failed verification: size mismatch '
                .'(expected '.$expectedBytes.' bytes, got '.$actualBytes.').'
            );
        }

        $stream = Storage::disk($dest)->readStream($encRel);
        if ($stream === null) {
            throw new BackupException('Off-site upload of '.$encRel.' to ['.$dest.'] could not be read back for verification.');
        }

        $hashContext = hash_init('sha256');
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk !== false && $chunk !== '') {
                hash_update($hashContext, $chunk);
            }
        }
        fclose($stream);

        $actualSha256 = hash_final($hashContext);

        if (! hash_equals($expectedSha256, $actualSha256)) {
            throw new BackupException(
                'Off-site upload of '.$encRel.' to ['.$dest.'] failed verification: sha256 mismatch — '
                .'the remote object is corrupt.'
            );
        }
    }

    /** Warn (don't block) if PII would leave the EU on an S3 destination. */
    private function warnIfNonEuOffsite(string $disk): void
    {
        if ((string) config("filesystems.disks.{$disk}.driver") !== 's3') {
            return;
        }

        $region = (string) config("filesystems.disks.{$disk}.region");
        if ($region !== '' && ! in_array($region, self::EU_REGIONS, true)) {
            Log::channel(config('updates.log_channel', 'stack'))->warning(
                'Backup destination S3 region ['.$region.'] is outside the EU — customer PII '
                .'residency (GDPR) requires an EU region.', ['disk' => $disk]
            );
        }
    }
}
