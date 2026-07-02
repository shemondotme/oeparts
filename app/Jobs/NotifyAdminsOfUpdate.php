<?php

namespace App\Jobs;

use App\Mail\UpdateAvailableMail;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Update & Recovery System (Module 21, Chunk 1.4) — emails every active
 * super_admin that a new release is available. Dispatched by the scheduled
 * CheckForUpdates command (with per-version dedupe), so it runs at most once
 * per new version. 'default' queue (rule #16).
 */
class NotifyAdminsOfUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    /** @param array<string,mixed> $status UpdateStatus::toArray() */
    public function __construct(public array $status)
    {
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
            Mail::to($email)->send(new UpdateAvailableMail($this->status));
        }
    }
}
