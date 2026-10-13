<?php

namespace App\Services\Migration\Package;

/**
 * A short code a person can read aloud or compare, derived from the whole package:
 * XXXX-XXXX-XXXX-XXXX (80 bits, Crockford base32).
 *
 * It is the first 10 bytes of HMAC(fingerprint key, SHA-256(table of contents) + package id).
 * Because the table of contents lists every entry with its hash, and the key comes from the
 * passphrase, two people who see the same code have the same package and the same passphrase.
 * Shown when an export finishes and on the share page; an import can require it, so a package
 * swapped on the way is stopped by a human check even if the passphrase happens to be right.
 */
final class Fingerprint
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function compute(string $fingerprintKey, string $tocPlaintext, string $packageId): string
    {
        $mac = hash_hmac('sha256', hash('sha256', $tocPlaintext, true).$packageId, $fingerprintKey, true);

        return implode('-', str_split(self::crockford32(substr($mac, 0, 10)), 4));
    }

    /** Crockford base32 of the bytes, upper case, no padding. Ten bytes give exactly 16 characters. */
    public static function crockford32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    /**
     * Compare what a person typed with the real code: case-insensitive, separators ignored, and
     * the look-alike letters Crockford defines (O is 0, I and L are 1) forgiven.
     */
    public static function matches(string $typed, string $actual): bool
    {
        return hash_equals(self::normalise($actual), self::normalise($typed));
    }

    /** Like normalise() but keeps the hyphens, so the shape of a grouped code can still be checked. */
    public static function normaliseKeepingHyphens(string $code): string
    {
        return strtr(strtoupper(preg_replace('/[ \t]+/', '', $code) ?? ''), ['O' => '0', 'I' => '1', 'L' => '1']);
    }

    public static function normalise(string $code): string
    {
        $code = strtoupper(preg_replace('/[\s\-]+/', '', $code) ?? '');

        return strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);
    }
}
