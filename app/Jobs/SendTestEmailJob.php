<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTestEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $recipientEmail,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        Mail::raw('This is a test email from OeParts admin panel. Your SMTP configuration is working correctly.', function ($message) {
            $message->to($this->recipientEmail)
                ->subject('[OeParts] SMTP Test Email — ' . now()->format('Y-m-d H:i'));
        });
    }
}
