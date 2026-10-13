<?php

namespace App\Services\Migration\Export;

use App\Models\BackupRun;
use App\Services\Backup\BackupProgress;
use App\Services\Migration\MigrationProgress;

/**
 * Shows an export the way every other migration run is shown: the engine's BackupProgress becomes a
 * MigrationProgress, with the stage names turned into words a person would use.
 */
final class ProgressPresenter
{
    private const PHASES = [
        'plan' => ['preparing', 'Getting ready'],
        'db' => ['database', 'Copying the database'],
        'files' => ['files', 'Copying the files'],
        'verify' => ['checking', 'Checking the copy'],
        'pack' => ['packaging', 'Packing everything into one file'],
    ];

    private const ORDER = ['plan', 'db', 'files', 'verify', 'pack'];

    public function present(BackupProgress $progress, BackupRun $run): MigrationProgress
    {
        $meta = (array) $run->meta;
        $report = (array) ($meta['export_report'] ?? []);
        $stage = $progress->stage;
        [$phase, $label] = self::PHASES[$stage] ?? ['', ''];

        $status = match ($progress->status) {
            BackupRun::STATUS_SUCCESS => MigrationProgress::SUCCESS,
            BackupRun::STATUS_FAILED => MigrationProgress::FAILED,
            default => MigrationProgress::RUNNING,
        };

        $started = $run->started_at;

        return new MigrationProgress(
            runId: (string) $run->getKey(),
            kind: 'export',
            status: $status,
            rawPercent: (float) $progress->percent,
            phase: $phase,
            phaseIndex: $stage !== null && in_array($stage, self::ORDER, true) ? (int) array_search($stage, self::ORDER, true) + 1 : 0,
            phaseCount: count(self::ORDER),
            message: $status === MigrationProgress::SUCCESS ? 'The package is ready.' : ($progress->message ?? $label),
            counters: [
                'rows' => (int) ($report['db']['rows_total'] ?? 0),
                'rowsTotal' => (int) ($meta['export_plan']['estimate']['total_rows'] ?? 0),
                'files' => (int) ($report['files']['archived'] ?? 0),
                'filesTotal' => (int) ($meta['export_plan']['estimate']['files']['count'] ?? 0),
                'bytes' => (int) $progress->totalBytes,
            ],
            elapsedSeconds: $started !== null ? max(0, (int) $started->diffInSeconds($run->finished_at ?? now(), true)) : 0,
            error: $status === MigrationProgress::FAILED ? ['code' => (string) ($meta['error_code'] ?? 'import_failed_step'), 'params' => []] : null,
            warnings: array_values(array_map(static fn (array $w): string => (string) ($w['id'] ?? ''), (array) ($meta['export_plan']['warnings'] ?? []))),
            resumable: $status === MigrationProgress::RUNNING,
        );
    }
}
