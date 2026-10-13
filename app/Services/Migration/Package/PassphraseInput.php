<?php

namespace App\Services\Migration\Package;

/**
 * Where a package's passphrase comes from and whether it is good enough.
 *
 * The passphrase is the ONLY thing protecting the customer data in a package in transit. A
 * stolen file can be attacked offline for as long as the attacker likes, so the rule depends on
 * how expensive one guess is:
 *
 *   Argon2id (memory-hard)   at least 14 characters
 *   PBKDF2 (cheap on GPUs)   the GENERATED passphrase, or one of at least 20 characters that
 *                            also scores at least 80 bits on the estimate below
 *
 * Every door (installer, admin panel, command line) gets the same rule because it lives here.
 */
final class PassphraseInput
{
    public const MIN_LENGTH_ARGON2 = 14;

    public const MIN_LENGTH_PBKDF2 = 20;

    public const MIN_BITS_PBKDF2 = 80.0;

    /** Characters in the generated passphrase: 24 x 5 bits = 120 bits. */
    public const GENERATED_CHARS = 24;

    /** Lower case, no separators: words people reach for first. */
    private const COMMON = [
        'password', 'passw0rd', 'letmein', 'welcome', 'qwerty', 'iloveyou', 'admin', 'oeparts', 'trustno1',
        'monkey', 'dragon', 'football', 'baseball', 'abc123', '123456', 'correcthorsebatterystaple', 'changeme',
    ];

    public const OK = null;

    public const EMPTY = 'empty';

    public const TOO_SHORT = 'too_short';

    public const WEAK_FOR_PBKDF2 = 'weak_for_pbkdf2';

    /** A new passphrase: 24 random Crockford characters in six groups, 120 bits. */
    public static function generate(): string
    {
        $chars = '';
        for ($i = 0; $i < self::GENERATED_CHARS; $i++) {
            $chars .= Fingerprint::ALPHABET[random_int(0, 31)];
        }

        return implode('-', str_split($chars, 4));
    }

    /** Does it have the shape of a generated passphrase (six groups of four Crockford characters)? */
    public static function isGeneratedShape(string $passphrase): bool
    {
        return (bool) preg_match('/^(?:[0-9A-HJKMNP-TV-Z]{4}-){5}[0-9A-HJKMNP-TV-Z]{4}$/', Fingerprint::normaliseKeepingHyphens($passphrase));
    }

    /**
     * The passphrases to try, in order. Passphrase bytes are used literally; only when the input
     * has the shape of a generated passphrase are the forgiving forms (upper case, O to 0, I and L
     * to 1, stray spaces) tried too, because those are the typing slips of a code read from paper.
     *
     * @return array<int, string>
     */
    public static function candidates(string $typed): array
    {
        $candidates = [$typed];

        $canonical = Fingerprint::normaliseKeepingHyphens($typed);
        if ($canonical !== $typed && preg_match('/^(?:[0-9A-HJKMNP-TV-Z]{4}-){5}[0-9A-HJKMNP-TV-Z]{4}$/', $canonical)) {
            $candidates[] = $canonical;
        }

        return $candidates;
    }

    /**
     * @return string|null null when acceptable, otherwise one of the reason constants
     */
    public static function validate(string $passphrase, int $kdfId): ?string
    {
        if ($passphrase === '') {
            return self::EMPTY;
        }

        $length = mb_strlen($passphrase);

        if ($kdfId === PackageFormat::KDF_ARGON2ID) {
            return $length >= self::MIN_LENGTH_ARGON2 ? self::OK : self::TOO_SHORT;
        }

        if (self::isGeneratedShape($passphrase)) {
            return self::OK;
        }

        if ($length < self::MIN_LENGTH_PBKDF2) {
            return self::TOO_SHORT;
        }

        return self::estimateBits($passphrase) >= self::MIN_BITS_PBKDF2 ? self::OK : self::WEAK_FOR_PBKDF2;
    }

    /**
     * A deliberately pessimistic guess of the strength of a typed passphrase, in bits: the size of the
     * alphabet it draws from times its effective length, where repeated characters count half and a run
     * of consecutive characters (abcd, 4321) adds only its first one; anything built on a common
     * password is rejected outright. It never claims more than the passphrase really has.
     */
    public static function estimateBits(string $passphrase): float
    {
        $compact = preg_replace('/[\s\-_.]+/', '', mb_strtolower($passphrase)) ?? '';

        foreach (self::COMMON as $common) {
            if (str_contains($compact, $common) && mb_strlen($compact) <= mb_strlen($common) + 6) {
                return 0.0;
            }
        }

        $chars = mb_str_split($passphrase);
        if ($chars === []) {
            return 0.0;
        }

        $pool = 0;
        if (preg_match('/[a-z]/', $passphrase)) {
            $pool += 26;
        }
        if (preg_match('/[A-Z]/', $passphrase)) {
            $pool += 26;
        }
        if (preg_match('/\d/', $passphrase)) {
            $pool += 10;
        }
        if (preg_match('/[^a-zA-Z\d]/', $passphrase)) {
            $pool += 33;
        }
        $pool = max($pool, 2);

        $unique = count(array_unique($chars));
        $effective = $unique + 0.5 * (count($chars) - $unique);

        // Runs of consecutive code points (abcd, 1234, 4321) are one character of information, not four.
        $run = 1;
        $previousDelta = 0;
        for ($i = 1, $n = count($chars); $i < $n; $i++) {
            $delta = mb_ord($chars[$i]) - mb_ord($chars[$i - 1]);

            if (abs($delta) === 1 && ($previousDelta === 0 || $delta === $previousDelta)) {
                $run++;
                $previousDelta = $delta;

                continue;
            }

            if ($run >= 4) {
                $effective -= ($run - 1);
            }
            $run = 1;
            $previousDelta = 0;
        }
        if ($run >= 4) {
            $effective -= ($run - 1);
        }

        return max(0.0, $effective) * log($pool, 2);
    }
}
