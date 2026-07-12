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

        $sentCount = 0;
        $failedCount = 0;

        // Chunked + batched (was: one create() + one status update() + one
        // increment() per subscriber — thousands of individual queries on a
        // large list, risking queue-worker timeouts). Recipient rows are
        // bulk-inserted per chunk, then their sent/failed status is written
        // back in at most two queries per chunk instead of one per row.
        NewsletterSubscriber::where('is_active', true)
            ->chunkById(self::CHUNK_SIZE, function ($subscribers) use (&$sentCount, &$failedCount) {
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

                $sentCount += count($sentIds);
                $failedCount += count($failedIds);
            });

        $this->campaign->update([
            'status'       => 'sent',
            'sent_at'      => now(),
            'sent_count'   => $sentCount,
            'failed_count' => $failedCount,
        ]);
    }

    /**
     * Bulk-insert a pending recipient row per subscriber in this chunk, then
     * fetch them back (one INSERT + one SELECT, instead of one create() per
     * subscriber) keyed by subscriber_id for the send loop above.
     */
    private function createRecipientsFor($subscribers): \Illuminate\Support\Collection
    {
        NewsletterCampaignRecipient::insert(
            $subscribers->map(fn ($subscriber) => [
                'campaign_id'   => $this->campaign->id,
                'subscriber_id' => $subscriber->id,
                'email'         => $subscriber->email,
                'status'        => 'pending',
            ])->all()
        );

        return NewsletterCampaignRecipient::where('campaign_id', $this->campaign->id)
            ->whereIn('subscriber_id', $subscribers->pluck('id'))
            ->get()
            ->keyBy('subscriber_id');
    }
}
