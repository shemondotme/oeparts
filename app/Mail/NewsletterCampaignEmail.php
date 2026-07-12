<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterCampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly NewsletterCampaign $campaign,
        public readonly NewsletterCampaignRecipient $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
            tags: ['newsletter', 'campaign'],
            metadata: [
                'campaign_id' => $this->campaign->id,
                'recipient_id' => $this->recipient->id,
            ],
        );
    }

    public function content(): Content
    {
        $locale = $this->recipient->subscriber?->lang ?: 'en';
        $unsubscribeUrl = route('frontend.newsletter.unsubscribe', [
            'lang' => $locale,
            'token' => $this->recipient->subscriber?->unsubscribe_token,
        ]);

        return new Content(
            view: 'emails.newsletter-campaign',
            text: 'emails.newsletter-campaign-text',
            with: [
                'campaign'   => $this->campaign,
                'recipient'  => $this->recipient,
                'locale'     => $locale,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
