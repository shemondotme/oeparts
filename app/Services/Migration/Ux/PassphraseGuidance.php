<?php

namespace App\Services\Migration\Ux;

use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PassphraseInput;

/**
 * What the export wizard tells a person about the passphrase they are about to choose, and what it refuses.
 *
 * The rule depends on how this server can lock a package. Argon2id (needs the sodium extension) is expensive to guess at, so a
 * passphrase of at least 14 characters is enough. Without it the package is locked with PBKDF2, which is cheaper for an attacker,
 * so only the passphrase we generate, or one of at least 20 strong characters, is accepted (PassphraseInput holds the real test;
 * this class only picks the words). The KDF is a constructor argument so a test can speak for a server of either kind.
 */
final class PassphraseGuidance
{
    public function __construct(private readonly ?int $kdfId = null) {}

    public function kdfId(): int
    {
        return $this->kdfId ?? Kdf::preferredKdfId();
    }

    public function isArgon2(): bool
    {
        return $this->kdfId() === PackageFormat::KDF_ARGON2ID;
    }

    public function minLength(): int
    {
        return $this->isArgon2() ? PassphraseInput::MIN_LENGTH_ARGON2 : PassphraseInput::MIN_LENGTH_PBKDF2;
    }

    /** One plain sentence: how this server locks packages and what that asks of the passphrase. */
    public function describe(): string
    {
        return __('migration.ui.export.'.($this->isArgon2() ? 'kdf_argon2' : 'kdf_pbkdf2'), ['min' => $this->minLength()]);
    }

    /** The reason a typed passphrase is refused, or null when it is good enough. */
    public function problemWith(string $passphrase): ?string
    {
        $problem = PassphraseInput::validate($passphrase, $this->kdfId());

        if ($problem === null) {
            return null;
        }

        return match ($problem) {
            PassphraseInput::EMPTY => __('migration.ui.export.passphrase_empty'),
            PassphraseInput::TOO_SHORT => __('migration.ui.export.'.($this->isArgon2() ? 'passphrase_short_argon2' : 'passphrase_short_pbkdf2'), ['min' => $this->minLength()]),
            default => __('migration.ui.export.passphrase_weak'),
        };
    }
}
