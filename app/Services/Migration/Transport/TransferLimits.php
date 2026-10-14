<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\MigrationPaths;

/** The numbers a package download works within, read from config in one place and shown by `oeparts:migrate:limits`. */
class TransferLimits
{
    public function maxBytes(): int
    {
        return max(1, (int) config('migration.transport.max_bytes', 32 * 1024 ** 3));
    }

    public function rangeBytes(): int
    {
        return max(64 * 1024, (int) config('migration.transport.range_bytes', 32 * 1024 ** 2));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('migration.transport.max_attempts', 8));
    }

    /** Seconds to wait after the Nth failed attempt (1-based); the last value repeats. */
    public function backoffSeconds(int $attempt): int
    {
        $steps = array_values(array_map('intval', (array) config('migration.transport.backoff_seconds', [5, 15, 45, 120, 300])));

        return $steps === [] ? 30 : $steps[min(max(1, $attempt), count($steps)) - 1];
    }

    /** One call of a web request or a poll: never more than half the web server's own time limit. */
    public function requestBudgetSeconds(): float
    {
        $budget = (float) config('migration.transport.budget_seconds', 10);
        $limit = (int) ini_get('max_execution_time');

        return $limit > 0 ? min($budget, $limit / 2) : $budget;
    }

    public function jobBudgetSeconds(): float
    {
        return (float) config('migration.transport.job_budget_seconds', 25);
    }

    public function verifySliceBytes(): int
    {
        return max(1024 * 1024, (int) config('migration.transport.verify_slice_bytes', 64 * 1024 ** 2));
    }

    /** Free bytes on the disk the package lands on; null when the system cannot tell. */
    public function freeBytes(): ?int
    {
        MigrationPaths::ensureDirectory(MigrationPaths::inbox());
        $free = @disk_free_space(MigrationPaths::inbox());

        return $free === false ? null : (int) $free;
    }

    /** The room a download of $remaining more bytes must find on the disk: the bytes themselves and a margin. */
    public function neededBytes(int $remaining): int
    {
        return $remaining + (int) max(64 * 1024 ** 2, $remaining * 0.02);
    }

    /** @return array<string, int|float|string|null> */
    public function describe(): array
    {
        return [
            'max_package_bytes' => $this->maxBytes(),
            'bytes_per_request' => $this->rangeBytes(),
            'attempts' => $this->maxAttempts(),
            'request_budget_seconds' => $this->requestBudgetSeconds(),
            'job_budget_seconds' => $this->jobBudgetSeconds(),
            'free_bytes' => $this->freeBytes(),
            'inbox' => MigrationPaths::inbox(),
            'private_hosts_allowed' => (bool) config('migration.transport.allow_private_hosts', false) ? 'yes (set in .env)' : 'no',
        ];
    }
}
