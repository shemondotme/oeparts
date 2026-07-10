<?php

namespace App\Jobs;

use App\Enums\PartInquiryStatus;
use App\Mail\PartInquiryStatusUpdate;
use App\Models\PartInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPartInquiryStatusEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly PartInquiry $inquiry,
        public readonly PartInquiryStatus $newStatus,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (blank($this->inquiry->email)) {
            return;
        }

        Mail::to($this->inquiry->email)
            ->send(new PartInquiryStatusUpdate($this->inquiry, $this->newStatus, $this->locale));
    }
}
