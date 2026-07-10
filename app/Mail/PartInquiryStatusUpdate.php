<?php

namespace App\Mail;

use App\Enums\PartInquiryStatus;
use App\Models\PartInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartInquiryStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PartInquiry $inquiry,
        public readonly PartInquiryStatus $newStatus,
        string $locale = 'en',
    ) {
        $this->locale = $locale;
    }

    public function envelope(): Envelope
    {
        $subjectKey = $this->newStatus === PartInquiryStatus::Sourced
            ? 'emails.part_inquiry_status.subject_sourced'
            : 'emails.part_inquiry_status.subject_unavailable';

        return new Envelope(
            subject: trans($subjectKey, ['oem' => $this->inquiry->oem_number], $this->locale),
            tags: ['part-inquiry-status'],
            metadata: [
                'inquiry_id'    => $this->inquiry->id,
                'template_type' => 'part_inquiry_status',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.part-inquiry-status',
            text: 'emails.part-inquiry-status-text',
            with: [
                'inquiry'   => $this->inquiry,
                'newStatus' => $this->newStatus,
                'locale'    => $this->locale,
            ],
        );
    }
}
