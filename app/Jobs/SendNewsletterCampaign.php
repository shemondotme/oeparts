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

    /** Subscribers per chunk — bounds memory + query size on large lists. */
    private const CHUNK_SIZE = 500;

    public function handle(): void
    {
        $this->campaign->update(['status' => 'sending']);

        // A retry after a mid-run failure (tries = 3) used to re-select
        // every active subscriber from scratch and re-insert/re-queue a
        // send for every one of them — including subscribers a prior,
        // partially-completed attempt had already emailed. Excluding
        // already-'sent' recipients here makes a retry pick up only where
        // it left off. Subscribers whose only prior row is 'pending' or
        // 'failed' are intentionally retried.
        NewsletterSubscriber::where('is_active', true)
            ->whereNotIn('id', function ($query) {
                $query->select('subscriber_id')
                    ->from('newsletter_campaign_recipients')
                    ->where('campaign_id', $this->campaign->id)
                    ->where('status', 'sent');
            })
            ->chunkById(self::CHUNK_SIZE, function ($subscribers) {
                $recipients = $this->createRecipientsFor($subscribers);

                $sentIds = [];
                $failedIds = [];

                foreach ($subscribers as $subscriber) {
                    $recipient = $recipients->get($subscriber->id);

                    try {
                        Mail::to($subscriber->email)
                            ->queue(new NewsletterCampaignEmail($this->campaign, $recipient));

                        $sentIds[] = $recipient->id;
                    } catch (\Exception $e) {
                        $failedIds[] = $recipient->id;
                    }
                }

                if ($sentIds !== []) {
                    NewsletterCampaignRecipient::whereIn('id', $sentIds)
                        ->update(['status' => 'sent', 'sent_at' => now()]);
                }
                if ($failedIds !== []) {
                    NewsletterCampaignRecipient::whereIn('id', $failedIds)->update(['status' => 'failed']);
                }
            });

        // Computed fresh from the recipients table (not accumulated in-job)
        // so a retry's totals reflect every attempt, not just this run's —
        // an earlier partial run's already-'sent' rows must still count.
        $this->campaign->update([
            'status'       => 'sent',
            'sent_at'      => now(),
            'sent_count'   => NewsletterCampaignRecipient::where('campaign_id', $this->campaign->id)->where('status', 'sent')->count(),
            'failed_count' => NewsletterCampaignRecipient::where('campaign_id', $this->campaign->id)->where('status', 'failed')->count(),
        ]);
    }

    /**
     * Upsert a pending recipient row per subscriber in this chunk, then
     * fetch them back keyed by subscriber_id for the send loop above.
     * upsert() (not insert()) matters on a retry: the outer query already
     * excludes 'sent' subscribers, but one with an existing 'pending' or
     * 'failed' row from a prior attempt would otherwise collide with the
     * unique (campaign_id, subscriber_id) index instead of being reused.
     */
    private function createRecipientsFor($subscribers): \Illuminate\Support\Collection
    {
        NewsletterCampaignRecipient::upsert(
            $subscribers->map(fn ($subscriber) => [
                'campaign_id'   => $this->campaign->id,
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
                'status'        => 'pending',
            ])->all(),
            ['campaign_id', 'subscriber_id'],
            ['email', 'status'],
        );

        return NewsletterCampaignRecipient::where('campaign_id', $this->campaign->id)
            ->whereIn('subscriber_id', $subscribers->pluck('id'))
            ->get()
            ->keyBy('subscriber_id');
    }
}
