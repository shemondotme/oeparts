<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Mail\BankTransferInstructions;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBankTransferInstructionsEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly Order $order,
        public readonly ?string $locale = null,
    ) {
        $this->onQueue('critical');
    }

    public function handle(PaymentService $paymentService): void
    {
        $toEmail = $this->order->user?->email ?? $this->order->guest_email;

        if (empty($toEmail)) {
            Log::warning('Skipped bank transfer instructions email: no recipient address', ['order_id' => $this->order->id]);

            return;
        }

        // Nothing to pay any more (cancelled, or the transfer already landed).
        if (! $this->order->status->canBeCancelled() || $this->order->payment_status === PaymentStatus::Paid) {
            return;
        }

        try {
            $bank = $paymentService->getBankTransferDetails($this->order);
        } catch (\RuntimeException $e) {
            // Bank details not configured: the storefront payment page cannot
            // show them either. Say so loudly instead of mailing an empty box.
            Log::error('Bank transfer instructions email not sent: bank details are not configured', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Mail::to($toEmail)->send(new BankTransferInstructions($this->order, $bank, $this->locale ?? $this->order->mailLocale()));
    }
}
