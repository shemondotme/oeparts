<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Update & Recovery System (Module 21, Chunk 2.6) — runs a backup off the request
 * cycle (the "Run backup now" admin action dispatches this). Delegates to the
 * oeparts:backup command so there is ONE orchestration path (start → run →
 * fail-alert → retention). On the sync queue (shared hosting) it runs inline.
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string $profile = 'full',
        public string $trigger = 'manual',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Artisan::call('oeparts:backup', [
            '--profile' => $this->profile,
            '--trigger' => $this->trigger,
        ]);
    }
}
