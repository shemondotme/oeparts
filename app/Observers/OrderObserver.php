<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->log($order, 'created', [], $order->getAttributes());
        $this->invalidateCache($order);
    }

    public function updated(Order $order): void
    {
        $original = $order->getOriginal();
        $changes = $order->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($order, 'updated', $original, $changes);
        }

        $this->invalidateCache($order);
    }

    public function deleted(Order $order): void
    {
        $this->log($order, 'deleted', $order->getAttributes(), []);
        $this->invalidateCache($order);
    }

    protected function invalidateCache(Order $order): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("order.{$order->id}");
            $cache->forget("order_number.{$order->order_number}");
            $cache->forget('admin:dashboard:recent_orders');
            $cache->forget('admin:dashboard:order_status_distribution');
            Cache::forget('admin:dashboard:kpi_stats');
            Cache::forget('admin:dashboard:checkout_dropoff');
            Cache::forget('admin:dashboard:payment_method_split');
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(Order $order, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($order),
                'model_id' => $order->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
