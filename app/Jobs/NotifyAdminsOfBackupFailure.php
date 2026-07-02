<?php

namespace App\Jobs;

use App\Mail\BackupFailedMail;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Update & Recovery System (Module 21, Chunk 2.6) — emails active super_admins
 * that a backup failed. 'default' queue (rule #16).
 */
class NotifyAdminsOfBackupFailure implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public string $profile,
        public string $reason,
        public ?int $runId = null,
        public ?string $failedAt = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $recipients = Admin::role('super_admin')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique();

        foreach ($recipients as $email) {
            Mail::to($email)->send(new BackupFailedMail($this->profile, $this->reason, $this->runId, $this->failedAt));
        }
    }
}
