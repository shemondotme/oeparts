<?php

namespace App\Services\Migration;

use App\Services\Migration\Package\DerivedKeys;

/** What a KeyEscrow holds for one run. */
final class EscrowEntry
{
    public function __construct(
        public readonly string $id,
        public readonly string $packageIdHex,
        public readonly DerivedKeys $keys,
        public readonly int $createdAt,
        public readonly int $expiresAt,
    ) {}
}
