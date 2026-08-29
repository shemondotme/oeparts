<?php

namespace App\Observers;

use App\Enums\RedirectType;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Redirect;
use App\Observers\Concerns\PushesToIndexNow;
use App\Services\CacheService;
use App\Services\ProductSlugService;
use App\Services\SearchService;
use App\Services\WidgetPreferenceService;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    use PushesToIndexNow;

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

        if (array_key_exists('is_active', $changes)) {
            if (! $changes['is_active'] && ($original['is_active'] ?? true)) {
                $this->createFallbackRedirects($product);
            } elseif ($changes['is_active'] && ! ($original['is_active'] ?? true)) {
                $this->removeFallbackRedirects($product);
            }
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
        $this->createFallbackRedirects($product);
        $this->invalidateCache($product);
    }

    /**
     * A discontinued/deactivated product's own detail URL used to just sit
     * there until a real visitor or crawler hit it, got logged as a 404,
     * and an admin eventually noticed and manually created a redirect via
     * NotFoundLogResource — every indexed product page silently bled
     * traffic/backlink value in the meantime.
     *
     * Scoped to the DETAIL url only, never the OEM hub url
     * (/parts/{oem}): the hub page for this OEM can still legitimately
     * serve OTHER active products sharing the same normalized_oem, and —
     * unlike the detail url's per-product numeric id, which is never
     * reused — a hub-level redirect created here would keep hijacking
     * that OEM's hub page forever even after new stock arrives under the
     * same number.
     */
    protected function createFallbackRedirects(Product $product): void
    {
        try {
            $manufacturer = $product->manufacturer;

            foreach (LocaleRegistry::codes() as $locale) {
                $fromUrl = $this->detailUrlPath($product, $locale);
                $toUrl = $manufacturer
                    ? route('frontend.manufacturer.show', ['lang' => $locale, 'manufacturer' => $manufacturer->slug])
                    : route('frontend.search.console', ['lang' => $locale]);

                Redirect::firstOrCreate(
                    ['from_url' => $fromUrl],
                    ['to_url' => $toUrl, 'type' => RedirectType::Permanent, 'is_active' => true]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to create fallback redirect for a discontinued/deactivated product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Undoes createFallbackRedirects() when a product is reactivated — an
     * admin flipping is_active back on shouldn't leave the product's own
     * detail url permanently shadowed by a stale redirect away from
     * itself (HandleRedirects would otherwise intercept it before the
     * product's own is_active/canonical logic in SearchController::detail()
     * ever runs).
     */
    protected function removeFallbackRedirects(Product $product): void
    {
        try {
            foreach (LocaleRegistry::codes() as $locale) {
                Redirect::where('from_url', $this->detailUrlPath($product, $locale))->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to remove fallback redirect for a reactivated product', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Same shape HandleRedirects looks up (Request::path(), lowercased,
     * no leading/trailing slash) for this product's per-locale detail url —
     * computed from the product's CURRENT field values, so this must be
     * called before those values are lost (deleted()'s $product still
     * carries them; updated()'s reflects the just-saved state).
     */
    protected function detailUrlPath(Product $product, string $locale): string
    {
        $idSlug = app(ProductSlugService::class)->buildIdSlug($product, $locale);

        return strtolower(trim("{$locale}/parts/{$product->normalized_oem}/{$idSlug}", '/'));
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

        // Separate try/catch from the cache-forget block above: a
        // Guzzle/queue failure here is a different failure domain than a
        // cache-store failure, and keeping them independent means one
        // failing never masks whether the other also failed — both stay
        // individually non-fatal to the CRUD operation that triggered them.
        $this->pushToIndexNow(array_map(
            fn (string $locale) => route('frontend.search.results', ['lang' => $locale, 'oem' => $product->normalized_oem]),
            LocaleRegistry::codes()
        ));
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
