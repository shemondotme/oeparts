<?php

namespace App\Services\Migration;

use App\Security\PackageAudit;
use App\Services\Migration\Import\ImportReportBuilder;
use App\Services\Migration\Import\ImportStateStore;
use App\Support\AtomicJsonFile;

/**
 * A READER over what the migration feature has recorded: the runs, their history lines and their reports. It never writes:
 * the history file has exactly one writer (PackageAudit::record) and a report has exactly one (ImportReportBuilder).
 */
final class MigrationLedger
{
    public function __construct(
        private readonly ImportStateStore $store,
        private readonly PackageAudit $audit,
        private readonly ImportReportBuilder $reports,
    ) {}

    /**
     * Every import run on this site, newest first, in a few plain fields.
     *
     * @return array<int, array{id: string, kind: string, status: string, started_at: string, finished_at: string|null, has_report: bool}>
     */
    public function runs(): array
    {
        $runs = [];

        foreach ($this->store->all() as $state) {
            $runs[] = [
                'id' => $state->id,
                'kind' => $state->kind,
                'status' => $state->status->value,
                'started_at' => $state->createdAt,
                'finished_at' => $state->finishedAt,
                'has_report' => is_file($this->reports->path($state->id)),
            ];
        }

        return $runs;
    }

    /** @return array<int, array<string, mixed>> the history lines of one run, oldest first */
    public function history(string $runId): array
    {
        return $this->audit->history($runId);
    }

    /** The written report of a run, or null when none was written (yet). */
    public function report(string $runId): ?array
    {
        if (! ImportStateStore::validId($runId) || ! is_file($this->reports->path($runId))) {
            return null;
        }

        return (new AtomicJsonFile($this->reports->path($runId)))->read();
    }
}
