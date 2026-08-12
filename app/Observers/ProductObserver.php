<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\ProductSlugService;
use App\Services\SearchService;
use App\Services\WidgetPreferenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->log($product, 'created', [], $product->getAttributes());
        $this->refreshSlug($product);
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

        if (array_intersect(array_keys($changes), ['name', 'manufacturer_id', 'condition_id'])) {
            $this->refreshSlug($product);
        }

        $this->invalidateCache($product);
    }

    /**
     * Keeps products.slug (always the English-locale slug — sitemap
     * generation has no per-request "current locale" to compute one from)
     * in sync when a field that feeds ProductSlugService changes. The
     * detail page itself never reads this column for its own URL — it
     * calls ProductSlugService::generate() fresh in the visitor's current
     * locale — this stored copy exists purely for the sitemap.
     *
     * updateQuietly() (not save()) so this doesn't re-fire created/updated
     * and recurse into itself.
     */
    protected function refreshSlug(Product $product): void
    {
        try {
            $slug = app(ProductSlugService::class)->generate($product, 'en');

            if ($product->slug !== $slug) {
                $product->updateQuietly(['slug' => $slug]);
            }
        } catch (\Throwable $e) {
            // Slug refresh must not break CRUD
        }
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
            $cache->forgetSearchConsoleStats();
            // Previously only invalidated by the bulk CSV import path and a
            // manual "Clear" click on the Cache Dashboard — a single product
            // created/edited/deleted through the admin panel (not the
            // importer) left the homepage hero "parts indexed" stat and
            // popular-OEMs strip stale for up to 6h/1h.
            $cache->forgetHeroStats();
            $cache->forgetPopularOems();
            Cache::forget('sitemap_parts');
            SearchService::bumpCacheVersion();

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
