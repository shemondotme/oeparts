<?php

namespace App\Jobs;

use App\Mail\AbandonedCartReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public string $email,
        public array $cartSnapshot,
        public ?string $customerName = null,
        public string $locale = 'en',
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $mailable = new AbandonedCartReminder(
            $this->cartSnapshot,
            $this->locale,
            $this->customerName,
        );

        Mail::to($this->email)->send($mailable);
    }
}
