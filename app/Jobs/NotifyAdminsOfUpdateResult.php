<?php

namespace App\Jobs;

use App\Mail\UpdateResultMail;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Update & Recovery System — emails every active super_admin the outcome of
 * an update apply, whether it was a scheduled unattended security auto-apply
 * (App\Console\Commands\AutoApplySecurityUpdate) or an admin-triggered manual
 * apply (App\Filament\Pages\System\SystemUpdates::startApply()). Dispatched
 * exactly once per attempt, success or failure — 'default' queue (rule #16).
 *
 * Formerly NotifyAdminsOfAutoUpdate — generalized because a manually-clicked
 * apply that fails while the admin's tab is closed previously notified
 * nobody; the old name/copy ("unattended"/"automatically") would have been
 * actively misleading for that case, so this dispatches for both triggers
 * and $result['trigger'] ('auto'|'manual') picks the right wording.
 */
class NotifyAdminsOfUpdateResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    /** @param array<string,mixed> $result see UpdateResultMail */
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
            Mail::to($email)->send(new UpdateResultMail($this->result));
        }
    }
}
