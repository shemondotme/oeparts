<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;

class LogOrderStatusChange
{
    public function handle(OrderStatusChanged $event): void
    {
        try {
            ActivityLog::create([
                'admin_id'    => auth('admin')->id(),
                'action'      => 'order_status_changed',
                'model_type'  => \App\Models\Order::class,
                'model_id'    => $event->order->id,
                'old_values'  => ['status' => $event->oldStatus?->value],
                'new_values'  => ['status' => $event->newStatus->value],
                'ip_address'  => request()->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log order status change', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
