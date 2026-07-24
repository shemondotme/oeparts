<?php

namespace App\Jobs;

use App\Mail\FatalErrorMail;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every active super_admin about an uncaught exception in the admin
 * panel (App\Exceptions\AdminFatalErrorNotifier). 'default' queue (rule #16).
 */
class NotifyAdminsOfFatalError implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    /** @param array<string,mixed> $error exception_class, message, file, line, url, occurred_at */
    public function __construct(public array $error)
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
            Mail::to($email)->send(new FatalErrorMail($this->error));
        }
    }
}
