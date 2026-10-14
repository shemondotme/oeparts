<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\KeyEscrow;

/**
 * What a stage is given besides the state: how long it may work, the passphrase if the caller has one right now
 * (held in memory for this call only, never written anywhere), who is asking (for the wrong-passphrase throttle),
 * and the services every stage needs.
 */
final class ImportContext
{
    public function __construct(
        public readonly float $deadline,
        public readonly KeyEscrow $escrow,
        public readonly ?string $passphrase = null,
        public readonly string $clientKey = 'cli',
    ) {}

    public function secondsLeft(): float
    {
        return max(0.0, $this->deadline - microtime(true));
    }

    public function expired(): bool
    {
        return microtime(true) >= $this->deadline;
    }

    /** The escrow entry id of a run's keys. */
    public static function escrowId(string $runId): string
    {
        return 'import-'.$runId;
    }
}
