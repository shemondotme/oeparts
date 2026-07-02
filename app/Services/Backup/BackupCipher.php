<?php

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\BackupException;

/**
 * BackupCipher (Module 14/21, Chunk 2.4) — streamed AES-256-GCM for backup parts.
 *
 * Backups hold customer PII, so encryption is MANDATORY (GDPR, CLAUDE.md rule #45)
 * and keyed on the DEDICATED OE_BACKUP_KEY (never APP_KEY). Losing the key loses
 * every backup — the app warns loudly and refuses to back up without one.
 *
 * Format (framed AEAD — streams in flat memory, no whole-file buffering):
 *   header : "OEENC1" + version byte
 *   frame* : iv(12) · tag(16) · len(uint32 BE) · ciphertext(len)
 * Each ≤1 MB plaintext block is encrypted as an independent GCM frame with the
 * frame index as AAD, so blocks can't be dropped, reordered, or tampered without
 * the authentication failing. Decryption reproduces the ORIGINAL bytes exactly —
 * so a decrypted volume's plaintext byte offsets (Chunk 2.3 segment map) stay
 * valid for single-file extraction on restore.
 */
class BackupCipher
{
    private const MAGIC   = 'OEENC1';
    private const VERSION = 1;
    private const CIPHER  = 'aes-256-gcm';
    private const BLOCK   = 1048576; // 1 MB plaintext per frame
    private const IV_LEN  = 12;
    private const TAG_LEN = 16;

    /** Is a backup key configured? Pre-flight / the engine block a backup without one. */
    public function hasKey(): bool
    {
        return trim((string) config('backup.encryption.key')) !== '';
    }

    /** A fresh, correctly-formatted key suggestion for the loud "set this" warning. */
    public function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /** Encrypt $src → $dst (both absolute paths). Returns integrity metadata. */
    public function encryptFile(string $src, string $dst): array
    {
        $key = $this->key();

        $in  = @fopen($src, 'rb');
        $out = @fopen($dst, 'wb');
        if ($in === false || $out === false) {
            throw new BackupException('Could not open files for encryption.');
        }

        fwrite($out, self::MAGIC.chr(self::VERSION));

        $plainCtx   = hash_init('sha256');
        $plainBytes = 0;
        $frames     = 0;

        while (! feof($in)) {
            $block = fread($in, self::BLOCK);
            if ($block === false || $block === '') {
                break;
            }

            hash_update($plainCtx, $block);
            $plainBytes += strlen($block);

            $iv  = random_bytes(self::IV_LEN);
            $tag = '';
            $ct  = openssl_encrypt(
                $block, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, pack('N', $frames), self::TAG_LEN
            );

            if ($ct === false) {
                fclose($in);
                fclose($out);
                throw new BackupException('AES-256-GCM encryption failed.');
            }

            fwrite($out, $iv.$tag.pack('N', strlen($ct)).$ct);
            $frames++;
        }

        fclose($in);
        fclose($out);

        return [
            'cipher'       => self::CIPHER,
            'frames'       => $frames,
            'plain_bytes'  => $plainBytes,
            'plain_sha256' => hash_final($plainCtx),
            'enc_bytes'    => (int) filesize($dst),
            'enc_sha256'   => hash_file('sha256', $dst),
        ];
    }

    /** Decrypt $src → $dst (both absolute paths). Throws on any auth failure. */
    public function decryptFile(string $src, string $dst): void
    {
        $in  = @fopen($src, 'rb');
        $out = @fopen($dst, 'wb');
        if ($in === false || $out === false) {
            throw new BackupException('Could not open files for decryption.');
        }

        try {
            $this->decryptStream($in, $out);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** Decrypt an in-memory ciphertext blob (used for small parts like manifests). */
    public function decryptData(string $encrypted): string
    {
        $in = fopen('php://temp', 'r+b');
        fwrite($in, $encrypted);
        rewind($in);

        $out = fopen('php://temp', 'r+b');

        try {
            $this->decryptStream($in, $out);
            rewind($out);

            return (string) stream_get_contents($out);
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private function decryptStream($in, $out): void
    {
        $key    = $this->key();
        $header = $this->readExact($in, strlen(self::MAGIC) + 1, allowEof: true);

        if (substr($header, 0, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new BackupException('Not an OeParts encrypted backup stream.');
        }

        $frame = 0;

        while (true) {
            $iv = $this->readExact($in, self::IV_LEN, allowEof: true);
            if ($iv === '') {
                break; // clean EOF between frames
            }

            $tag = $this->readExact($in, self::TAG_LEN);
            $len = unpack('N', $this->readExact($in, 4))[1];
            $ct  = $this->readExact($in, $len);

            $pt = openssl_decrypt(
                $ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, pack('N', $frame)
            );

            if ($pt === false) {
                throw new BackupException('Backup decryption/authentication failed at frame '.$frame.'.');
            }

            fwrite($out, $pt);
            $frame++;
        }
    }

    /** Read exactly $len bytes (handling short reads); '' only on clean EOF when allowed. */
    private function readExact($handle, int $len, bool $allowEof = false): string
    {
        $buffer = '';
        while (strlen($buffer) < $len) {
            $chunk = fread($handle, $len - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }

        if ($buffer === '' && $allowEof) {
            return '';
        }

        if (strlen($buffer) !== $len) {
            throw new BackupException('Corrupt encrypted backup stream (truncated).');
        }

        return $buffer;
    }

    /** Derive the 32-byte AES key from OE_BACKUP_KEY (mandatory). */
    private function key(): string
    {
        $raw = trim((string) config('backup.encryption.key'));

        if ($raw === '') {
            throw new BackupException(
                'OE_BACKUP_KEY is not set. Backups are mandatorily encrypted (GDPR — customer PII). '
                .'Set OE_BACKUP_KEY in .env (e.g. '.$this->generateKey().') and store it somewhere safe: '
                .'losing this key makes every backup permanently unrecoverable.'
            );
        }

        return hash('sha256', $raw, true);
    }
}
