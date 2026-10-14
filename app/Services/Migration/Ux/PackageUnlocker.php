<?php

namespace App\Services\Migration\Ux;

use App\Security\PassphraseThrottle;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\Exceptions\PackageException;
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
    public function unlock(string $path, string $passphrase, string $clientKey): array
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
}
