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
        return new Content(
            htmlString: $this->campaign->html_content,
            text: 'emails.newsletter-campaign-text',
            with: [
                'campaign'   => $this->campaign,
                'recipient'  => $this->recipient,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
