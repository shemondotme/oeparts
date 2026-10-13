<?php

namespace App\Services\Migration\Package;

/**
 * What can be known about a file from its first bytes and its last 64, with no passphrase:
 * the format, the key-derivation settings, the package id and whether the file looks complete.
 * It works on a half-uploaded file too, which is what lets an upload screen say what it is
 * receiving before the transfer ends.
 */
final class PackagePeek
{
    public function __construct(
        public readonly Header $header,
        public readonly int $fileSize,
        /** The file ends with a trailer that has both signatures. It does not prove the file is intact. */
        public readonly bool $hasTrailer,
    ) {}

    public function packageIdHex(): string
    {
        return bin2hex($this->header->packageId);
    }
}
