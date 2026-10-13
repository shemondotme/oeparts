<?php

namespace App\Services\Migration\Package;

/**
 * The four subkeys of a package, all derived from the passphrase's master key.
 *
 *   check    proves the passphrase before anything else is read (the key-check in the header)
 *   data     encrypts every ordinary entry
 *   secrets  encrypts the optional secrets entry, so reading it is a separate, explicit act
 *   fp       the fingerprint a person can compare
 *
 * The HKDF salt is the SHA-256 of the header's authenticated bytes, so flipping ANY header byte
 * (the KDF settings, the salt, the package id, a flag) changes every subkey. A header that was
 * altered, for example to weaken the key derivation, can therefore never produce a matching
 * key-check.
 */
final class DerivedKeys
{
    /** @var array<string, string> */
    private array $keys = [];

    public function __construct(string $master, string $headerAuthenticatedBytes)
    {
        $headerSalt = hash('sha256', $headerAuthenticatedBytes, true);

        foreach (PackageFormat::subkeyNames() as $name) {
            $this->keys[$name] = hash_hkdf('sha256', $master, 32, PackageFormat::HKDF_INFO_PREFIX.$name, $headerSalt);
        }
    }

    /**
     * Rebuild keys that were derived earlier (read back from the KeyEscrow), so a later request
     * does not pay for the key derivation again. The key-check in the header still proves they
     * belong to the package they are used on.
     *
     * @param  array<string, string>  $subkeys  name => 32 raw bytes, all four names required
     */
    public static function restore(array $subkeys): self
    {
        foreach (PackageFormat::subkeyNames() as $name) {
            if (! isset($subkeys[$name]) || strlen($subkeys[$name]) !== 32) {
                throw new \InvalidArgumentException("The stored key '{$name}' is missing or has the wrong length.");
            }
        }

        $keys = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        foreach (PackageFormat::subkeyNames() as $name) {
            $keys->keys[$name] = $subkeys[$name];
        }

        return $keys;
    }

    /**
     * The raw subkeys, for the KeyEscrow only: it wraps them with the site key before they touch disk.
     *
     * @return array<string, string>
     */
    public function subkeys(): array
    {
        return $this->keys;
    }

    public function check(): string
    {
        return $this->keys['check'];
    }

    public function data(): string
    {
        return $this->keys['data'];
    }

    public function secrets(): string
    {
        return $this->keys['secrets'];
    }

    public function fingerprint(): string
    {
        return $this->keys['fp'];
    }

    /** Overwrite the key material in memory (best effort: PHP may hold other copies). */
    public function wipe(): void
    {
        foreach ($this->keys as $name => $key) {
            if (function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
            $this->keys[$name] = '';
        }
    }
}
