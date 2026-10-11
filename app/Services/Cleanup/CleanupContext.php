<?php

namespace App\Services\Cleanup;

use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use Carbon\CarbonInterface;
use Closure;

/**
 * Everything a target needs to know about the run it is working for, handed to every
 * phase call. Read-only: a target reports progress through its checkpoint, never by
 * touching the run or item rows itself.
 */
final class CleanupContext
{
    /**
     * @param  Closure():bool  $cancelCheck  re-reads the run's cancel flag (cheap, one column)
     */
    public function __construct(
        public readonly CleanupRun $run,
        public readonly CleanupRunItem $item,
        public readonly CarbonInterface $cutoff,
        public readonly int $retentionDays,
        public readonly bool $dryRun,
        public readonly bool $truncate,
        public readonly int $maxChunk,
        public readonly int $sleepMs,
        public readonly ?Archiver $archiver,
        private readonly Closure $cancelCheck,
    ) {}

    public function cancelRequested(): bool
    {
        return ($this->cancelCheck)();
    }
}
