<?php

namespace App\Services\Migration\Package;

/**
 * The fixed constants of the .oeparts container, version 1.0. Nothing here is configurable on
 * purpose: a value that could differ between the machine that wrote a file and the machine that
 * reads it would be a way to produce files only one of them can open.
 */
final class PackageFormat
{
    public const MAGIC = 'OEPARTS1';

    /** CR LF SUB LF: any text-mode transfer (FTP ASCII, a CRLF-converting copy) destroys it. */
    public const TEXT_GUARD = "\x0D\x0A\x1A\x0A";

    public const FORMAT_MAJOR = 1;

    public const FORMAT_MINOR = 0;

    public const HEADER_LEN = 128;

    /** The key-check occupies the last 32 bytes of the header. */
    public const KEYCHECK_LEN = 32;

    public const SALT_LEN = 16;

    public const PACKAGE_ID_LEN = 16;

    /** There is no "none": a package that is not encrypted cannot be expressed. */
    public const CIPHER_AES_256_GCM = 1;

    public const KDF_ARGON2ID = 1;

    public const KDF_PBKDF2_SHA512 = 2;

    public const DEFAULT_BLOCK_LOG2 = 20;

    public const MIN_BLOCK_LOG2 = 16;

    public const MAX_BLOCK_LOG2 = 22;

    /** Header flag bits a reader that does not know them must refuse (v1: none are defined). */
    public const KNOWN_CRITICAL_FLAGS = 0;

    public const KEYCHECK_CONTEXT = 'OEPARTS1/keycheck';

    public const HKDF_INFO_PREFIX = 'oeparts/v1/';

    /** @return array<int, string> the named subkeys */
    public static function subkeyNames(): array
    {
        return ['check', 'data', 'secrets', 'fp'];
    }
}
