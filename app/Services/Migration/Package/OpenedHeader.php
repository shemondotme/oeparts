<?php

namespace App\Services\Migration\Package;

/** A header whose passphrase has been proven, with the keys that passphrase derives. */
final class OpenedHeader
{
    public function __construct(
        public readonly Header $header,
        public readonly DerivedKeys $keys,
    ) {}
}
