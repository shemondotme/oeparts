<?php

namespace App\Console\Commands;

use App\Services\CustomInvoiceService;
use Illuminate\Console\Command;

/**
 * Chases overdue custom invoices on the schedule set in Settings → Store & Commerce →
 * Invoice ("3,10,21" = 3, 10 and 21 days after the due date). Does nothing unless the
 * admin has switched automatic reminders on; a reminder can always be sent by hand.
 */
class SendInvoiceReminders extends Command
{
    protected $signature = 'oeparts:invoices:remind';

    protected $description = 'Email payment reminders for overdue custom invoices (only when enabled in settings)';

    public function handle(CustomInvoiceService $invoices): int
    {
        if (! filter_var(settings('invoice.reminders_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->info('Automatic invoice reminders are switched off in Settings; nothing to do.');

            return self::SUCCESS;
        }

        $sent = $invoices->sendDueReminders();

        $this->info("Sent {$sent} payment reminder(s).");

        return self::SUCCESS;
    }
}
