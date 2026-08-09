<?php

namespace App\Jobs;

use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCart;
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
        public int $abandonedCartId,
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

        // Flagged only once the send actually succeeds — CartRecoveryService
        // used to set this immediately after dispatch()'ing the job, before
        // the mail was ever attempted. If the send later failed (all 3
        // retries exhausted), the flag stayed permanently true, and
        // ProcessAbandonedCarts' "one recovery per customer per 7 days"
        // dedup check would then skip retrying a customer who never actually
        // received anything.
        AbandonedCart::where('id', $this->abandonedCartId)->update(['recovery_email_sent' => true]);
    }
}
