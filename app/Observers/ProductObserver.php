<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\WidgetPreferenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->log($product, 'created', [], $product->getAttributes());
        $this->invalidateCache($product);
    }

    public function updated(Product $product): void
    {
        $original = $product->getOriginal();
        $changes = $product->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($product, 'updated', $original, $changes);
        }

        $this->invalidateCache($product);
    }

    public function deleted(Product $product): void
    {
        $this->log($product, 'deleted', $product->getAttributes(), []);
        $this->invalidateCache($product);
    }

    protected function invalidateCache(Product $product): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("product.{$product->id}");
            $cache->forgetManufacturers();
            Cache::forget('sitemap_parts');

            foreach (['stock_alert', 'manufacturing_stats', 'new_products_added'] as $widgetId) {
                WidgetPreferenceService::forgetCache($widgetId);
            }
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(Product $product, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($product),
                'model_id' => $product->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
