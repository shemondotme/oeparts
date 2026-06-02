<?php

namespace App\Jobs;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public readonly NewsletterSubscriber $subscriber,
        public readonly string $confirmUrl,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Mail::to($this->subscriber->email)
            ->send(new NewsletterConfirmation($this->subscriber, $this->confirmUrl, $this->locale));
    }
}
