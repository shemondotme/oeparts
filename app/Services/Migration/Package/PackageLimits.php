<?php

namespace App\Services\Migration\Package;

/**
 * Hard limits applied while reading and writing a package. A package is untrusted input: every
 * count and length in it is checked against these BEFORE memory or disk is committed to it.
 */
final class PackageLimits
{
    public const MAX_ENTRIES = 100_000;

    /** One entry's plaintext. */
    public const MAX_ENTRY_BYTES = 4 * 1024 ** 3;

    /** One entry once decompressed: a compressed entry may announce more than its stored bytes, but not unboundedly more. */
    public const MAX_ENTRY_RAW_BYTES = 8 * self::MAX_ENTRY_BYTES;

    /** The whole file. */
    public const MAX_PACKAGE_BYTES = 2 * 1024 ** 4;

    /** Sum of every entry's decompressed size. */
    public const MAX_TOTAL_RAW_BYTES = 4 * 1024 ** 4;

    public const MAX_META_BYTES = 1024 ** 2;

    public const MAX_TOC_BYTES = 64 * 1024 ** 2;

    /** Entry names are labels, never file paths. */
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9._\/-]{0,191}$/';

    /** Names only the writer itself may create. */
    public const RESERVED_NAMES = ['meta', 'toc', 'secrets'];

    public const ENTRY_ID_META = 0;

    public const ENTRY_ID_TOC = 0xFFFFFFFF;

    public const TRAILER_LEN = 64;

    public const TRAILER_MAGIC = 'OEPTRL01';

    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1 && ! str_contains($name, '..');
    }
}
