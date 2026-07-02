<?php

namespace App\Services\Backup\Stages;

use App\Models\BackupPart;
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
 * disk (config('backup.staging_disk')). This stage, one part per step (rule #48),
 * AES-256-GCM-encrypts each staged part (BackupCipher), then:
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

        $part = $run->parts()
            ->where('id', '>', (int) $state['cursor_id'])
            ->orderBy('id')
            ->first();

        if (! $part) {
            return StageStepResult::complete(null, 'encrypt: all parts secured');
        }

        $this->securePart($run, $part);

        $state['cursor_id'] = (int) $part->getKey();

        return StageStepResult::progress($state, null, 'encrypt: '.$part->name);
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

        return ['init' => true, 'cursor_id' => 0];
    }

    private function securePart(BackupRun $run, BackupPart $part): void
    {
        // Idempotent: a resumed run must never re-encrypt an already-secured part.
        if (($part->meta['encrypted'] ?? false) === true) {
            return;
        }

        $srcDisk = $part->disk;                 // local staging
        $srcRel  = $part->path;
        $encRel  = $srcRel.'.enc';

        $srcAbs = Storage::disk($srcDisk)->path($srcRel);
        $encAbs = Storage::disk($srcDisk)->path($encRel);

        $meta = $this->cipher->encryptFile($srcAbs, $encAbs);

        $dest = $run->disk;                     // final destination (may be off-site)

        if ($dest === $srcDisk) {
            Storage::disk($srcDisk)->delete($srcRel); // keep the .enc locally
            $finalDisk = $srcDisk;
        } else {
            $handle = fopen($encAbs, 'rb');
            Storage::disk($dest)->writeStream($encRel, $handle);
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (! Storage::disk($dest)->exists($encRel)) {
                throw new BackupException('Off-site upload of '.$encRel.' to ['.$dest.'] could not be verified.');
            }

            Storage::disk($srcDisk)->delete($srcRel); // plaintext
            Storage::disk($srcDisk)->delete($encRel); // local encrypted copy
            $finalDisk = $dest;
        }

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
            ]),
        ]);
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
