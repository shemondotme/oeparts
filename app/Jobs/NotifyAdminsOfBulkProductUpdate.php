<?php

namespace App\Jobs;

use App\Mail\BulkUpdateAppliedMail;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Emails active super_admins when a bulk product update affects more than
 * BulkUpdateProducts::LARGE_BATCH_THRESHOLD rows. 'default' queue (rule #16),
 * same shape as NotifyAdminsOfBackupFailure.
 */
class NotifyAdminsOfBulkProductUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public string $adminName,
        public string $actionLabel,
        public int $affectedCount,
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
            Mail::to($email)->send(new BulkUpdateAppliedMail($this->adminName, $this->actionLabel, $this->affectedCount));
        }
    }
}
