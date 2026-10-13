<?php

namespace App\Services\Migration\Package;

use App\Services\Migration\Package\Exceptions\InsufficientResourcesException;
use App\Services\Migration\Package\Exceptions\KdfParamsRejectedException;
use App\Services\Migration\Package\Exceptions\KdfUnavailableException;
use App\Support\PhpSize;

/**
 * Turns a passphrase into the 32-byte master key.
 *
 *  kdf 1  Argon2id (libsodium): p1 = passes, p2 = memory in KiB. The default and the strong one.
 *  kdf 2  PBKDF2-HMAC-SHA512: p1 = iterations. The fallback for a host without ext-sodium.
 *
 * The parameters are read from the file, so they are attacker-controlled: they are checked
 * against hard bounds BEFORE the key derivation runs. Without that a hostile file could ask for
 * 100 GB of memory or ten billion iterations and take the server down just by being opened.
 */
final class Kdf
{
    public const ARGON2_PASSES_MIN = 2;

    public const ARGON2_PASSES_MAX = 12;

    public const ARGON2_MEMORY_KIB_MIN = 32 * 1024;

    public const ARGON2_MEMORY_KIB_MAX = 1024 * 1024;

    public const PBKDF2_MIN = 300_000;

    public const PBKDF2_MAX = 10_000_000;

    /** What a new package asks for. */
    public const ARGON2_PASSES_DEFAULT = 3;

    public const ARGON2_MEMORY_KIB_DEFAULT = 64 * 1024;

    /** The export never goes below this for PBKDF2 (a stolen file is attacked on GPUs). */
    public const PBKDF2_FLOOR = 1_000_000;

    /** Memory that must stay free on top of the Argon2 working set. */
    private const HEADROOM_BYTES = 32 * 1024 * 1024;

    /**
     * @param  array{passesMin: int, passesMax: int, memoryKibMin: int, memoryKibMax: int, pbkdf2Min: int, pbkdf2Max: int}|null  $bounds
     *                                                                                                                                    only tests pass this, to run the real algorithms with cheap parameters; production never does
     */
    public function __construct(private readonly ?array $bounds = null) {}

    /** @return array{passesMin: int, passesMax: int, memoryKibMin: int, memoryKibMax: int, pbkdf2Min: int, pbkdf2Max: int} */
    private function limits(): array
    {
        return $this->bounds ?? [
            'passesMin' => self::ARGON2_PASSES_MIN,
            'passesMax' => self::ARGON2_PASSES_MAX,
            'memoryKibMin' => self::ARGON2_MEMORY_KIB_MIN,
            'memoryKibMax' => self::ARGON2_MEMORY_KIB_MAX,
            'pbkdf2Min' => self::PBKDF2_MIN,
            'pbkdf2Max' => self::PBKDF2_MAX,
        ];
    }

    public static function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_pwhash') && defined('SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13');
    }

    /** The KDF a new package should use on this host. */
    public static function preferredKdfId(): int
    {
        return self::sodiumAvailable() ? PackageFormat::KDF_ARGON2ID : PackageFormat::KDF_PBKDF2_SHA512;
    }

    /**
     * Parameters for a NEW package.
     *
     * @return array{p1: int, p2: int}
     */
    public function defaultParams(int $kdfId): array
    {
        return match ($kdfId) {
            PackageFormat::KDF_ARGON2ID => ['p1' => self::ARGON2_PASSES_DEFAULT, 'p2' => self::ARGON2_MEMORY_KIB_DEFAULT],
            PackageFormat::KDF_PBKDF2_SHA512 => ['p1' => self::calibratePbkdf2Iterations(), 'p2' => 0],
            default => throw new KdfParamsRejectedException("Unknown key derivation {$kdfId}."),
        };
    }

    /**
     * Refuse parameters outside the bounds. Runs before any derivation.
     *
     * @throws KdfParamsRejectedException
     */
    public function assertAcceptable(int $kdfId, int $p1, int $p2): void
    {
        $l = $this->limits();

        if ($kdfId === PackageFormat::KDF_ARGON2ID) {
            if ($p1 < $l['passesMin'] || $p1 > $l['passesMax'] || $p2 < $l['memoryKibMin'] || $p2 > $l['memoryKibMax']) {
                throw new KdfParamsRejectedException("Argon2id parameters out of range (passes {$p1}, memory {$p2} KiB).");
            }

            return;
        }

        if ($kdfId === PackageFormat::KDF_PBKDF2_SHA512) {
            if ($p1 < $l['pbkdf2Min'] || $p1 > $l['pbkdf2Max']) {
                throw new KdfParamsRejectedException("PBKDF2 iteration count out of range ({$p1}).");
            }

            return;
        }

        throw new KdfParamsRejectedException("Unknown key derivation {$kdfId}.");
    }

    /**
     * @return string 32 raw bytes
     *
     * @throws KdfParamsRejectedException
     * @throws KdfUnavailableException
     * @throws InsufficientResourcesException
     */
    public function derive(string $passphrase, int $kdfId, string $salt, int $p1, int $p2): string
    {
        $this->assertAcceptable($kdfId, $p1, $p2);

        if (strlen($salt) !== PackageFormat::SALT_LEN) {
            throw new KdfParamsRejectedException('The salt must be exactly '.PackageFormat::SALT_LEN.' bytes.');
        }

        if ($kdfId === PackageFormat::KDF_ARGON2ID) {
            if (! self::sodiumAvailable()) {
                throw new KdfUnavailableException;
            }

            $this->assertMemoryFits($p2 * 1024);

            return sodium_crypto_pwhash(32, $passphrase, $salt, $p1, $p2 * 1024, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        }

        return hash_pbkdf2('sha512', $passphrase, $salt, $p1, 32, true);
    }

    /**
     * Iterations for a new PBKDF2 package: the higher of the floor and what takes about a second
     * on this host, capped at the accepted maximum.
     */
    public static function calibratePbkdf2Iterations(float $targetSeconds = 1.0): int
    {
        $probe = 100_000;
        $start = microtime(true);
        $digest = hash_pbkdf2('sha512', 'calibration', str_repeat("\0", PackageFormat::SALT_LEN), $probe, 32, true);
        $elapsed = max(microtime(true) - $start, 0.000_001);

        if (strlen($digest) !== 32) {
            throw new \RuntimeException('The PBKDF2 benchmark did not produce a key.');
        }

        $perSecond = (int) ($probe / $elapsed);
        $wanted = (int) round($perSecond * $targetSeconds);

        return max(self::PBKDF2_FLOOR, min($wanted, self::PBKDF2_MAX));
    }

    /** @throws InsufficientResourcesException */
    private function assertMemoryFits(int $workingSetBytes): void
    {
        $limit = PhpSize::toBytes(ini_get('memory_limit'));

        if ($limit === PHP_INT_MAX || $limit === 0) {
            return; // unlimited (or unreadable): nothing to compare against
        }

        $needed = $workingSetBytes + self::HEADROOM_BYTES;

        if ($needed > $limit) {
            $neededMb = (int) ceil($needed / 1024 / 1024);

            throw new InsufficientResourcesException(
                "Deriving the key needs about {$neededMb} MB, but memory_limit is ".ini_get('memory_limit').'.',
                $neededMb
            );
        }
    }
}
