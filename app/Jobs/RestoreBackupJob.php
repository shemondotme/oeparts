<?php

namespace App\Jobs;

use App\Models\BackupRun;
use App\Services\Backup\RestoreManager;
use App\Services\Backup\RestoreOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Update & Recovery System (Module 21, Chunk 2.6) — runs a restore off the
 * request cycle (the admin "Restore" action dispatches this after re-auth).
 * Defaults to a non-destructive files-only restore into storage/app/restore.
 */
class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    /** @param array<string,mixed> $options database|files|table|target_root */
    public function __construct(
        public int $runId,
        public array $options = [],
        public ?int $requestedBy = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(RestoreManager $restore): void
    {
        $run = BackupRun::find($this->runId);
        if (! $run) {
            return;
        }

        $report = $restore->restore($run, new RestoreOptions(
            database: (bool) ($this->options['database'] ?? false),
            files: (bool) ($this->options['files'] ?? true),
            table: $this->options['table'] ?? null,
            targetRoot: $this->options['target_root'] ?? null,
        ));

        Log::channel(config('updates.log_channel', 'stack'))->notice('Backup restore completed.', [
            'run'          => $this->runId,
            'requested_by' => $this->requestedBy,
            'report'       => $report->toArray(),
        ]);
    }
}
