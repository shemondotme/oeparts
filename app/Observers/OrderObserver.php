<?php

namespace App\Observers;

use App\Filament\Resources\OrderResource;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\CacheService;
use App\Services\WidgetPreferenceService;
use App\Support\AdminNotifier;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->log($order, 'created', [], $order->getAttributes());
        $this->invalidateCache($order);
        $this->notifyNewOrder($order);
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

    protected function notifyNewOrder(Order $order): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin', 'manager'],
                Notification::make()
                    ->title('New order placed')
                    ->body($order->order_number . ' · ' . format_money($order->grand_total))
                    ->icon('heroicon-o-shopping-bag')
                    ->iconColor('success')
                    ->actions([
                        Action::make('view')
                            ->label('View order')
                            ->url(OrderResource::getUrl('view', ['record' => $order->getKey()], panel: 'admin'))
                            ->markAsRead(),
                    ]),
            );
        } catch (\Throwable $e) {
            // A bell notification must never break checkout / order creation.
        }
    }

    protected function invalidateCache(Order $order): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("order.{$order->id}");
            $cache->forget("order_number.{$order->order_number}");

            foreach ([
                'dashboard_header',
                'order_stats_overview',
                'revenue_chart',
                'order_volume_chart',
                'order_status_distribution',
                'awaiting_confirmation',
                'recent_orders',
                'manufacturer_revenue',
            ] as $widgetId) {
                WidgetPreferenceService::forgetCache($widgetId);
            }
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
