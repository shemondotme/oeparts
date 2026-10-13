<?php

namespace App\Services\Migration;

use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\PackageFormat;

/**
 * Holds the keys of a package between requests, so the passphrase is typed and stretched once.
 *
 * An import or export advances in short requests (a browser poll, a queue job, a cron nudge);
 * none of them may keep the passphrase, and each re-deriving the keys would cost an Argon2id run
 * per poll. The four subkeys are written to storage/app/migration/escrow/<id>.key wrapped with
 * AES-256-GCM under a key derived from the site's APP_KEY, bound (as authenticated data) to the
 * run id, the package id and the expiry, with mode 0600 in a 0700 directory. They are never in a
 * session, a cookie, a state file or the database: those hold only the escrow id.
 *
 * What this protects and what it does not: a copy of the escrow file alone is useless without
 * APP_KEY; someone who can read both APP_KEY and the file already controls the site. The file
 * is deleted at every terminal state of a run, and prune() (a janitor hook) removes anything
 * older than the TTL even if the run that owned it died.
 */
final class KeyEscrow
{
    private const VERSION = 1;

    private const AAD_PREFIX = "OEESC1\0";

    private const WRAP_INFO = 'oeparts/escrow/v1';

    private const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/';

    private readonly string $wrapKey;

    /** @var \Closure(): int */
    private readonly \Closure $clock;

    public function __construct(
        private readonly string $directory,
        string $appKey,
        private readonly int $ttlSeconds = 86400,
        ?\Closure $clock = null,
    ) {
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;

        if ($raw === false || strlen($raw) < 16) {
            throw new \InvalidArgumentException('The site key (APP_KEY) is missing or too short to protect stored keys.');
        }

        if ($ttlSeconds < 60) {
            throw new \InvalidArgumentException('The escrow lifetime must be at least 60 seconds.');
        }

        $this->wrapKey = hash_hkdf('sha256', $raw, 32, self::WRAP_INFO);
        $this->clock = $clock ?? static fn (): int => time();
    }

    public static function forApplication(): self
    {
        return new self(
            MigrationPaths::escrow(),
            (string) config('app.key'),
            (int) config('migration.security.escrow_ttl_seconds', 86400),
        );
    }

    /**
     * Store the keys of one package under a run id, replacing any earlier entry of that id.
     *
     * @throws \RuntimeException when the file cannot be written: a run must not go on without its key
     */
    public function put(string $id, string $packageIdHex, DerivedKeys $keys): void
    {
        $this->assertId($id);
        $this->assertPackageId($packageIdHex);

        $created = ($this->clock)();
        $expires = $created + $this->ttlSeconds;

        $plain = '';
        foreach (PackageFormat::subkeyNames() as $name) {
            $plain .= $keys->subkeys()[$name];
        }

        $iv = random_bytes(12);
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $this->wrapKey, OPENSSL_RAW_DATA, $iv, $tag, $this->aad($id, $packageIdHex, $created, $expires), 16);

        if ($ciphertext === false) {
            throw new \RuntimeException('Cannot wrap the keys for storage.');
        }

        $document = json_encode([
            'v' => self::VERSION, 'id' => $id, 'package_id' => $packageIdHex, 'created' => $created, 'expires' => $expires,
            'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'ct' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR);

        $this->writePrivateFile($this->pathOf($id), $document);
    }

    /**
     * The entry of a run, or null when there is none (never stored, deleted, or expired: an
     * expired entry is deleted on the way).
     *
     * @throws MigrationException KeyEscrowLost when the file exists but cannot be read or authenticated
     */
    public function get(string $id): ?EscrowEntry
    {
        $this->assertId($id);
        $path = $this->pathOf($id);

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        $document = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($document) || ($document['v'] ?? null) !== self::VERSION || ($document['id'] ?? null) !== $id
            || ! is_int($document['created'] ?? null) || ! is_int($document['expires'] ?? null)
            || ! is_string($document['package_id'] ?? null) || ! preg_match('/^[0-9a-f]{32}$/', $document['package_id'])) {
            throw new MigrationException(MigrationErrorCode::KeyEscrowLost);
        }

        if ($document['expires'] <= ($this->clock)()) {
            $this->delete($id);

            return null;
        }

        $plain = openssl_decrypt(
            (string) base64_decode((string) ($document['ct'] ?? ''), true), 'aes-256-gcm', $this->wrapKey, OPENSSL_RAW_DATA,
            (string) base64_decode((string) ($document['iv'] ?? ''), true), (string) base64_decode((string) ($document['tag'] ?? ''), true),
            $this->aad($id, $document['package_id'], $document['created'], $document['expires'])
        );

        if ($plain === false || strlen($plain) !== 32 * count(PackageFormat::subkeyNames())) {
            throw new MigrationException(MigrationErrorCode::KeyEscrowLost);
        }

        $subkeys = [];
        foreach (PackageFormat::subkeyNames() as $index => $name) {
            $subkeys[$name] = substr($plain, $index * 32, 32);
        }

        return new EscrowEntry($id, $document['package_id'], DerivedKeys::restore($subkeys), $document['created'], $document['expires']);
    }

    /**
     * Like get() but a missing entry is an error too: for a run that cannot go on without its key.
     *
     * @throws MigrationException
     */
    public function require(string $id): EscrowEntry
    {
        return $this->get($id) ?? throw new MigrationException(MigrationErrorCode::KeyEscrowLost);
    }

    /** Delete an entry. Safe to call twice; throws only when the file is there and cannot be removed. */
    public function delete(string $id): void
    {
        $this->assertId($id);
        $path = $this->pathOf($id);

        if (is_file($path) && ! @unlink($path) && is_file($path)) {
            throw new \RuntimeException("Cannot delete the stored key {$path}.");
        }
    }

    /**
     * Remove every entry past its expiry, every file that is not a readable entry and older than the
     * lifetime, and abandoned temporary files. A janitor calls this.
     *
     * @return int files removed
     */
    public function prune(): int
    {
        if (! is_dir($this->directory)) {
            return 0;
        }

        $now = ($this->clock)();
        $removed = 0;

        foreach (scandir($this->directory) ?: [] as $name) {
            $path = $this->directory.DIRECTORY_SEPARATOR.$name;

            if ($name === '.' || $name === '..' || ! is_file($path)) {
                continue;
            }

            $age = $now - (int) @filemtime($path);

            if (str_ends_with($name, '.tmp')) {
                $stale = $age > 3600;
            } elseif (str_ends_with($name, '.key')) {
                $document = json_decode((string) @file_get_contents($path), true);
                $stale = is_array($document) && is_int($document['expires'] ?? null)
                    ? $document['expires'] <= $now
                    : $age > $this->ttlSeconds;
            } else {
                $stale = $age > $this->ttlSeconds;
            }

            if ($stale && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    private function pathOf(string $id): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.$id.'.key';
    }

    private function aad(string $id, string $packageIdHex, int $created, int $expires): string
    {
        return self::AAD_PREFIX.$id."\0".$packageIdHex."\0".$created."\0".$expires;
    }

    private function assertId(string $id): void
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1 || str_contains($id, '..')) {
            throw new \InvalidArgumentException('An escrow id is letters, digits, dot, dash and underscore, at most 64 characters.');
        }
    }

    private function assertPackageId(string $packageIdHex): void
    {
        if (preg_match('/^[0-9a-f]{32}$/', $packageIdHex) !== 1) {
            throw new \InvalidArgumentException('The package id must be 32 hexadecimal characters.');
        }
    }

    private function writePrivateFile(string $path, string $contents): void
    {
        MigrationPaths::ensureDirectory($this->directory);

        $temporary = $this->directory.DIRECTORY_SEPARATOR.'.'.bin2hex(random_bytes(6)).'.tmp';
        $previousUmask = umask(0077);

        try {
            $handle = @fopen($temporary, 'xb');

            if ($handle === false) {
                throw new \RuntimeException("Cannot create {$temporary}.");
            }

            @chmod($temporary, 0600);
            $written = fwrite($handle, $contents);
            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }
            fclose($handle);

            if ($written !== strlen($contents) || ! @rename($temporary, $path)) {
                throw new \RuntimeException("Cannot store the key at {$path}.");
            }
        } catch (\Throwable $e) {
            @unlink($temporary);

            throw $e;
        } finally {
            umask($previousUmask);
        }
    }
}
