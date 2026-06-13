<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogPaymentReceived
{
    public function handle(PaymentReceived $event): void
    {
        try {
            ActivityLog::create([
                'admin_id'    => auth('admin')->id() ?? null,
                'action'      => 'payment_received',
                'model_type'  => \App\Models\Order::class,
                'model_id'    => $event->order->id,
                'old_values'  => ['payment_status' => $event->order->getOriginal('payment_status')],
                'new_values'  => [
                    'payment_status'    => $event->payment->status->value,
                    'payment_reference' => $event->payment->transaction_id,
                    'amount'            => $event->payment->amount,
                ],
                'ip_address'  => request()->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log payment received', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
