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

        foreach ($due as $campaign) {
            dispatch(new SendNewsletterCampaign($campaign));
            $this->info("Dispatched campaign #{$campaign->id}: {$campaign->subject}");
        }

        if ($due->isEmpty()) {
            $this->info('No campaigns due.');
        }

        return self::SUCCESS;
    }
}
