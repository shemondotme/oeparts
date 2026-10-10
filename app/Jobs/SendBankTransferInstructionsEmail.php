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
        // Dispatched from inside the order / payment transaction. On a real queue
        // (Redis / database) the worker can pick the job up BEFORE that transaction
        // commits and render the mail from the order as it was before it (no
        // invoice number, old status) or not find a new order at all — so wait for
        // the commit. The sync connection runs the job inline, inside the
        // transaction, where the data is already visible; deferring it there would
        // only move a mail failure out of the caller's try/catch.
        if (config('queue.default') !== 'sync') {
            $this->afterCommit();
        }
    }

    public function handle(PaymentService $paymentService): void
    {
        $toEmail = $this->order->recipientEmail();

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
