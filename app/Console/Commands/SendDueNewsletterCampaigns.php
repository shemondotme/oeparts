<?php

namespace App\Console\Commands;

use App\Jobs\SendNewsletterCampaign;
use App\Models\NewsletterCampaign;
use Illuminate\Console\Command;

class SendDueNewsletterCampaigns extends Command
{
    protected $signature = 'oeparts:newsletter:send-due';

    protected $description = 'Dispatch newsletter campaigns whose scheduled send time has arrived';

    public function handle(): int
    {
        $due = NewsletterCampaign::query()
            ->whereIn('status', ['draft', 'scheduled'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->whereNull('sent_at')
            ->get();

        $dispatched = 0;

        foreach ($due as $campaign) {
            // Atomically claim the campaign before dispatching. status only
            // flipped to 'sending' inside the job itself, not here — if the
            // queue is backed up past the next 5-minute tick (or "Send Now"
            // is double-clicked), the same still-draft/scheduled campaign
            // got re-selected and re-dispatched, racing two jobs against the
            // same subscriber list. An UPDATE ... WHERE status IN (...)
            // affecting exactly one row proves this run won the claim.
            $claimed = NewsletterCampaign::whereKey($campaign->id)
                ->whereIn('status', ['draft', 'scheduled'])
                ->update(['status' => 'sending']);

            if ($claimed === 0) {
                continue;
            }

            dispatch(new SendNewsletterCampaign($campaign));
            $dispatched++;
            $this->info("Dispatched campaign #{$campaign->id}: {$campaign->subject}");
        }

        if ($dispatched === 0) {
            $this->info('No campaigns due.');
        }

        return self::SUCCESS;
    }
}
