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
    private const MAGIC = 'OEENC1';

    private const VERSION = 1;

    private const CIPHER = 'aes-256-gcm';

    private const BLOCK = 1048576; // 1 MB plaintext per frame

    private const IV_LEN = 12;

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

        // @ keeps fopen()'s warning out of the logs, but inside a Laravel app
        // error_get_last() is ALWAYS null afterwards (the framework's own error
        // handler "handles" the warning, and PHP then records nothing) — so the
        // old `error_get_last()['message'] ?? 'unknown reason'` could only ever
        // say "unknown reason" (seen on a live update: "products.data.325.sql.gz
        // (unknown reason)"). Work out the reason by inspecting the path instead.
        $in = @fopen($src, 'rb');
        if ($in === false) {
            throw new BackupException("Could not open source file for reading: {$src} (".$this->openFailureReason($src, false).').');
        }

        $out = @fopen($dst, 'wb');
        if ($out === false) {
            fclose($in);

            throw new BackupException("Could not open destination file for writing: {$dst} (".$this->openFailureReason($dst, true).').');
        }

        fwrite($out, self::MAGIC.chr(self::VERSION));

        $plainCtx = hash_init('sha256');
        $plainBytes = 0;
        $frames = 0;

        while (! feof($in)) {
            $block = fread($in, self::BLOCK);
            if ($block === false || $block === '') {
                break;
            }

            hash_update($plainCtx, $block);
            $plainBytes += strlen($block);

            $iv = random_bytes(self::IV_LEN);
            $tag = '';
            $ct = openssl_encrypt(
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
            'cipher' => self::CIPHER,
            'frames' => $frames,
            'plain_bytes' => $plainBytes,
            'plain_sha256' => hash_final($plainCtx),
            'enc_bytes' => (int) filesize($dst),
            'enc_sha256' => hash_file('sha256', $dst),
        ];
    }

    /**
     * Why fopen() failed on $path, worked out from the filesystem — see the
     * comment in encryptFile() for why error_get_last() can't be used here.
     */
    private function openFailureReason(string $path, bool $forWriting): string
    {
        $who = function_exists('posix_geteuid') && function_exists('posix_getpwuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid())
            : get_current_user();
        $perms = static function (string $p): string {
            $mode = @fileperms($p);
            $owner = function_exists('posix_getpwuid') && ($uid = @fileowner($p)) !== false
                ? (posix_getpwuid($uid)['name'] ?? (string) $uid)
                : '?';

            return $mode === false ? 'unknown perms' : sprintf('mode %04o, owner %s', $mode & 0777, $owner);
        };

        $dir = dirname($path);

        if (! $forWriting) {
            if (is_dir($path)) {
                return 'it is a directory, not a file';
            }
            if (! file_exists($path)) {
                return is_dir($dir)
                    ? "file does not exist — its folder exists, so it was deleted or never written (e.g. cleaned up by another run) [PHP user: {$who}]"
                    : "file and its folder do not exist — the run's directory was removed [PHP user: {$who}]";
            }
            if (! is_readable($path)) {
                return "file exists but PHP user '{$who}' cannot read it ({$perms($path)})";
            }

            return "file exists and looks readable ({$perms($path)}) but could not be opened [PHP user: {$who}]";
        }

        if (! is_dir($dir)) {
            return 'destination folder does not exist';
        }
        if (! is_writable($dir)) {
            return "folder is not writable by PHP user '{$who}' ({$perms($dir)})";
        }
        if (file_exists($path) && ! is_writable($path)) {
            return "file exists but is not writable by PHP user '{$who}' ({$perms($path)})";
        }
        $free = @disk_free_space($dir);
        if ($free !== false && $free < 1048576) {
            return 'the disk is full ('.round($free / 1024).' KB free)';
        }

        return "could not be opened for writing [PHP user: {$who}, folder {$perms($dir)}]";
    }

    /** Decrypt $src → $dst (both absolute paths). Throws on any auth failure. */
    public function decryptFile(string $src, string $dst): void
    {
        $in = @fopen($src, 'rb');
        $out = @fopen($dst, 'wb');
        if ($in === false || $out === false) {
            $in === false || fclose($in);
            $out === false || fclose($out);

            throw new BackupException(
                'Could not open files for decryption: '
                .($in === false ? "{$src} (".$this->openFailureReason($src, false).')' : "{$dst} (".$this->openFailureReason($dst, true).')')
            );
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
        $key = $this->key();
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

            // GCM ciphertext is exactly as long as the plaintext block that
            // produced it, so nothing legitimate ever exceeds self::BLOCK. A
            // corrupted or tampered stream with an implausible length would
            // otherwise make readExact() accumulate an attacker/corruption
            // -controlled amount of memory before authentication even gets a
            // chance to fail.
            if ($len > self::BLOCK) {
                throw new BackupException('Corrupt encrypted backup stream (frame '.$frame.' declares an implausible length).');
            }

            $ct = $this->readExact($in, $len);

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
