<?php

namespace App\Services\Migration\Ux;

use App\Security\PassphraseThrottle;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;

/**
 * Turns a typed passphrase into the package's keys, the one way every door does it: through the wrong-passphrase
 * throttle (counted per package and per client, locked after a few misses), with the forgiving forms of a generated
 * passphrase tried, and with every refusal reported as a plain-language migration error rather than a reader's
 * exception. A wrong passphrase is always called wrong, never "the file is damaged".
 */
final class PackageUnlocker
{
    public function __construct(
        private readonly PassphraseThrottle $throttle,
        private readonly Kdf $kdf,
    ) {}

    public static function forApplication(): self
    {
        return new self(PassphraseThrottle::forApplication(), app(Kdf::class));
    }

    /**
     * @return array{keys: DerivedKeys, package_id: string, fingerprint: string}
     *
     * @throws MigrationException PassphraseEmpty, PassphraseWrong, PassphraseLocked, a file problem, or a KDF refusal
     */
    public function unlock(string $path, #[\SensitiveParameter] string $passphrase, string $clientKey): array
    {
        if (trim($passphrase) === '') {
            throw new MigrationException(MigrationErrorCode::PassphraseEmpty);
        }

        try {
            $peek = PackageReader::peek($path, $this->kdf);
            $reader = $this->throttle->attempt(
                $peek->packageIdHex(),
                $clientKey,
                fn (): PackageReader => PackageReader::openTyped($path, $passphrase, $this->kdf),
            );
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            return ['keys' => $reader->keys(), 'package_id' => $reader->packageIdHex(), 'fingerprint' => $reader->fingerprint()];
        } finally {
            $reader->close();
        }
    }

    /**
     * A guess at a name that is not a finished package (no such file, still arriving, not a package): counted in the passphrase
     * throttle like a wrong passphrase, and answered the same way. Used when the file list is switched off, so that the answer says nothing
     * about which names exist.
     *
     * @throws MigrationException always: PassphraseWrong, or PassphraseLocked while the client is locked
     */
    public function refuseUnknownName(string $clientKey): never
    {
        try {
            $this->throttle->attempt('unknown-name', $clientKey, static function (): never {
                throw new WrongPassphraseException;
            });
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        throw new MigrationException(MigrationErrorCode::PassphraseWrong);
    }

    /**
     * Prove that keys derived EARLIER open THIS file, with no passphrase and no key derivation. The reader checks them against the
     * file itself: the header's key check (a keyed MAC over every header byte) and then the authenticated table of contents, so the
     * keys of another package, or of a file that was changed since, are refused. The fingerprint comes from the file, never from the
     * caller. There is no guess to throttle: the keys come from the server's own escrow, not from a person.
     *
     * @return array{keys: DerivedKeys, package_id: string, fingerprint: string}
     *
     * @throws MigrationException the keys do not open this file
     */
    public function adopt(string $path, DerivedKeys $keys): array
    {
        try {
            $reader = PackageReader::openWithKeys($path, $keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        try {
            return ['keys' => $reader->keys(), 'package_id' => $reader->packageIdHex(), 'fingerprint' => $reader->fingerprint()];
        } finally {
            $reader->close();
        }
    }
}
