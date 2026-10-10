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

class ExpireUnpaidBankTransferOrders extends Command
{
    protected $signature = 'oeparts:orders:expire-unpaid';

    protected $description = 'Cancel bank-transfer orders still unpaid after the configured limit (Settings → Orders Policy), releasing their stock';

    public function handle(OrderService $orders): int
    {
        $hours = (int) settings('orders.bank_transfer_expiry_hours', 48);

        if ($hours <= 0) {
            $this->info('Bank transfer expiry disabled (orders.bank_transfer_expiry_hours is 0).');

            return self::SUCCESS;
        }

        $cancelled = 0;

        Order::query()
            ->where('status', OrderStatus::Pending)
            ->where('payment_method', PaymentMethod::BankTransfer)
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->where('created_at', '<=', now()->subHours($hours))
            ->chunkById(100, function ($due) use ($orders, $hours, &$cancelled) {
                foreach ($due as $order) {
                    try {
                        $expired = DB::transaction(function () use ($orders, $order, $hours) {
                            // Re-read under a lock: the transfer may have been
                            // confirmed (by an admin or a bank sync) since the
                            // SELECT above, and a paid order must never be cancelled.
                            $fresh = Order::where('id', $order->id)->lockForUpdate()->first();

                            if (! $fresh
                                || $fresh->status !== OrderStatus::Pending
                                || $fresh->payment_status === PaymentStatus::Paid) {
                                return false;
                            }

                            $orders->transitionStatus(
                                $fresh,
                                OrderStatus::Cancelled,
                                "Bank transfer not received within {$hours} hours — order expired.",
                            );

                            $fresh->payments()
                                ->where('gateway', PaymentGateway::BankTransfer)
                                ->where('status', PaymentTransactionStatus::Pending)
                                ->update(['status' => PaymentTransactionStatus::Failed]);

                            return true;
                        });

                        if ($expired) {
                            $cancelled++;
                            $this->info("Order {$order->order_number} expired and cancelled.");
                        }
                    } catch (\Throwable $e) {
                        // One bad order must not stop the rest of the sweep.
                        Log::error('Failed to expire unpaid bank transfer order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                        $this->error("Order {$order->order_number} could not be expired: {$e->getMessage()}");
                    }
                }
            });

        if ($cancelled === 0) {
            $this->info('No unpaid bank-transfer orders past the limit.');
        }

        return self::SUCCESS;
    }
}
