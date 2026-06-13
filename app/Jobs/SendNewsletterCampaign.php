<?php

namespace App\Jobs;

use App\Mail\NewsletterCampaignEmail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly NewsletterCampaign $campaign,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $this->campaign->update(['status' => 'sending']);

        $subscribers = NewsletterSubscriber::where('is_active', true)->get();

        foreach ($subscribers as $subscriber) {
            $recipient = NewsletterCampaignRecipient::create([
                'campaign_id'   => $this->campaign->id,
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
                'status'        => 'pending',
            ]);

            try {
                Mail::to($subscriber->email)
                    ->queue(new NewsletterCampaignEmail($this->campaign, $recipient));

                $recipient->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);

                $this->campaign->increment('sent_count');
            } catch (\Exception $e) {
                $recipient->update(['status' => 'failed']);
                $this->campaign->increment('failed_count');
            }
        }

        $this->campaign->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }
}
