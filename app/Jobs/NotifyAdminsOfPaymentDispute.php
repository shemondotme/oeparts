<?php

namespace App\Jobs;

use App\Enums\AdminNotificationCategory;
use App\Filament\Resources\OrderResource;
use App\Mail\PaymentDisputeMail;
use App\Models\Admin;
use App\Services\AdminNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Alert-only: a gateway dispute/chargeback event notifies admins (bell, to
 * every active admin) and super_admins (email, matching the severity tier
 * already used for backup failures/fatal errors) but never changes order,
 * payment, or refund state automatically — [[project_bulletproof_testing_2026_09]]
 * Phase 10, the user's explicit choice over auto-hold/auto-cancel.
 */
class NotifyAdminsOfPaymentDispute implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public readonly string $eventType,
        public readonly ?int $orderId = null,
        public readonly ?string $orderNumber = null,
        public readonly ?string $disputeId = null,
        public readonly ?string $status = null,
        public readonly ?string $stage = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $reason = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(AdminNotificationService $notificationService): void
    {
        $notificationService->createForAll(
            category: AdminNotificationCategory::Orders,
            title: $this->orderNumber
                ? "Payment dispute on order {$this->orderNumber}"
                : 'Payment dispute received',
            detail: $this->summarize(),
            actionUrl: $this->orderId
                ? OrderResource::getUrl('view', ['record' => $this->orderId], panel: 'admin')
                : null,
        );

        $recipients = Admin::role('super_admin')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique();

        foreach ($recipients as $email) {
            Mail::to($email)->send(new PaymentDisputeMail(
                eventType: $this->eventType,
                orderNumber: $this->orderNumber,
                disputeId: $this->disputeId,
                status: $this->status,
                stage: $this->stage,
                amount: $this->amount,
                currency: $this->currency,
                reason: $this->reason,
            ));
        }
    }

    private function summarize(): string
    {
        $parts = [str_replace('dispute.', '', $this->eventType)];

        if ($this->status) {
            $parts[] = "status: {$this->status}";
        }
        if ($this->amount && $this->currency) {
            $parts[] = "{$this->amount} {$this->currency}";
        }

        return implode(' · ', $parts).'. No automatic action taken — review required.';
    }
}
