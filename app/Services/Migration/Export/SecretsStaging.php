<?php

namespace App\Services\Migration\Export;

/**
 * Where exported secrets wait between the database stage and the packaging stage, WITHOUT ever being
 * plaintext on disk. Each secret is one line, sealed with AES-256-GCM under the package's secrets key
 * (the same key the final `secrets` entry will use), so the staging file is useless to anyone who finds
 * it. The packaging stage reads the lines back, opens them with the same key and writes the real entry.
 *
 * The staging file is created with mode 0600 and removed by packaging (or by the janitor with the rest of
 * the run's staging).
 */
final class SecretsStaging
{
    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private readonly string $path,
        private readonly string $secretsKey,
    ) {
        if (strlen($secretsKey) !== 32) {
            throw new \InvalidArgumentException('The secrets key must be 32 bytes.');
        }
    }

    /** @param  array{group: string, key: string, value: string}  $secret */
    public function append(array $secret): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Cannot create the directory {$directory}.");
        }

        $iv = random_bytes(12);
        $plain = json_encode($secret, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, self::CIPHER, $this->secretsKey, OPENSSL_RAW_DATA, $iv, $tag, 'oeparts/secrets-staging/v1', 16);

        if ($cipher === false) {
            throw new \RuntimeException('Cannot seal a secret for staging.');
        }

        $isNew = ! is_file($this->path);
        $line = base64_encode($iv.$tag.$cipher)."\n";

        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
            throw new \RuntimeException('Short write to the secrets staging file: the disk may be full.');
        }

        if ($isNew) {
            @chmod($this->path, 0600);
        }
    }

    /**
     * Every staged secret, in the order they were staged.
     *
     * @return \Generator<int, array{group: string, key: string, value: string}>
     */
    public function read(): \Generator
    {
        if (! is_file($this->path)) {
            return;
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot read the secrets staging file.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $raw = base64_decode(trim($line), true);

                if ($raw === false || strlen($raw) < 28) {
                    throw new \RuntimeException('The secrets staging file is damaged.');
                }

                $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->secretsKey, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'oeparts/secrets-staging/v1');

                if ($plain === false) {
                    throw new \RuntimeException('A staged secret cannot be opened: wrong key or damaged file.');
                }

                yield json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
            }
        } finally {
            fclose($handle);
        }
    }

    /** Remove the staging file (best effort overwrite first, so a plain `undelete` finds less). */
    public function destroy(): void
    {
        if (! is_file($this->path)) {
            return;
        }

        $size = (int) @filesize($this->path);
        if ($size > 0 && ($handle = @fopen($this->path, 'r+b')) !== false) {
            fwrite($handle, str_repeat("\0", $size));
            fflush($handle);
            fclose($handle);
        }

        @unlink($this->path);
    }
}
