<?php

namespace App\Jobs;

use App\Mail\PartInquiryReceived;
use App\Models\PartInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPartInquiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly PartInquiry $inquiry,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $notifyEmail = settings('general.site_email', config('mail.from.address'));

        if (empty($notifyEmail)) {
            return;
        }

        Mail::to($notifyEmail)->send(new PartInquiryReceived($this->inquiry));
    }
}
