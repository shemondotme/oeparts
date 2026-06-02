<?php

namespace App\Jobs;

use App\Mail\ContactReply;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendContactReplyEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public readonly ContactMessage $message,
        public readonly string $replyBody,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Mail::to($this->message->email)
            ->send(new ContactReply($this->message, $this->replyBody, $this->locale));
    }
}
