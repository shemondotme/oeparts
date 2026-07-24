<?php

namespace App\Jobs;

use App\Mail\AutoUpdateResultMail;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Update & Recovery System — emails every active super_admin the outcome of
 * an unattended security-update auto-apply (App\Console\Commands\
 * AutoApplySecurityUpdate). Dispatched exactly once per run, success or
 * failure — 'default' queue (rule #16).
 */
class NotifyAdminsOfAutoUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    /** @param array<string,mixed> $result see AutoUpdateResultMail */
    public function __construct(public array $result)
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
            Mail::to($email)->send(new AutoUpdateResultMail($this->result));
        }
    }
}
