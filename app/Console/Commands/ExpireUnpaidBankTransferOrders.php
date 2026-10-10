<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancels orders that were placed but never paid, so they stop holding stock:
 *
 *  - bank transfer: after orders.bank_transfer_expiry_hours (default 48);
 *  - card / Paysera: after orders.online_payment_expiry_hours (default 24) —
 *    the customer abandoned the payment page. Orders with a held or captured
 *    payment are never touched. If a payment still arrives for an expired
 *    order, PaymentService settles it (refund queued / hold released).
 *
 * A limit of 0 turns that sweep off.
 */
class ExpireUnpaidBankTransferOrders extends Command
{
    protected $signature = 'oeparts:orders:expire-unpaid';

    protected $description = 'Cancel unpaid bank-transfer and abandoned online-payment orders past their limit (Settings → Orders Policy), releasing their stock';

    public function handle(OrderService $orders): int
    {
        $cancelled = $this->sweep(
            $orders,
            [PaymentMethod::BankTransfer],
            (int) settings('orders.bank_transfer_expiry_hours', 48),
            'Bank transfer not received within :hours hours — order expired.',
            PaymentGateway::BankTransfer,
        );

        $cancelled += $this->sweep(
            $orders,
            [PaymentMethod::Card, PaymentMethod::Paysera],
            (int) settings('orders.online_payment_expiry_hours', 24),
            'Online payment not completed within :hours hours — order expired.',
            null,
        );

        if ($cancelled === 0) {
            $this->info('No unpaid orders past their limit.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, PaymentMethod>  $methods
     */
    private function sweep(OrderService $orders, array $methods, int $hours, string $note, ?PaymentGateway $failGateway): int
    {
        if ($hours <= 0) {
            return 0;
        }

        $cancelled = 0;
        $note = str_replace(':hours', (string) $hours, $note);

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereIn('payment_method', $methods)
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->where('created_at', '<=', now()->subHours($hours))
            // Money already held or taken (e.g. a webhook still being processed)
            // means the order is not abandoned.
            ->whereDoesntHave('payments', fn ($q) => $q->whereIn('status', [
                PaymentTransactionStatus::Authorized,
                PaymentTransactionStatus::Captured,
            ]))
            ->chunkById(100, function ($due) use ($orders, $note, $failGateway, &$cancelled) {
                foreach ($due as $order) {
                    try {
                        $expired = DB::transaction(function () use ($orders, $order, $note, $failGateway) {
                            // Re-read under a lock: the payment may have been
                            // confirmed since the SELECT above, and a paid order
                            // must never be cancelled.
                            $fresh = Order::where('id', $order->id)->lockForUpdate()->first();

                            if (! $fresh
                                || $fresh->status !== OrderStatus::Pending
                                || $fresh->payment_status === PaymentStatus::Paid
                                || $fresh->payments()->whereIn('status', [
                                    PaymentTransactionStatus::Authorized,
                                    PaymentTransactionStatus::Captured,
                                ])->exists()) {
                                return false;
                            }

                            $orders->transitionStatus($fresh, OrderStatus::Cancelled, $note);

                            $pending = $fresh->payments()->where('status', PaymentTransactionStatus::Pending);
                            if ($failGateway) {
                                $pending->where('gateway', $failGateway);
                            }
                            $pending->update(['status' => PaymentTransactionStatus::Failed]);

                            return true;
                        });

                        if ($expired) {
                            $cancelled++;
                            $this->info("Order {$order->order_number} expired and cancelled.");
                        }
                    } catch (\Throwable $e) {
                        // One bad order must not stop the rest of the sweep.
                        Log::error('Failed to expire unpaid order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->error("Order {$order->order_number} could not be expired: {$e->getMessage()}");
                    }
                }
            });

        return $cancelled;
    }
}
