<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Builds the cosmetic slug segment for a product detail page URL
 * (/parts/{oem}/{id}-{slug}). Str::slug() already ASCII-transliterates,
 * matching this codebase's universal slug convention (CarModelResource,
 * ManufacturerResource, BlogPostResource) and the decided "Latin/ASCII
 * slug in every locale" requirement — a non-Latin product name still
 * produces a clean URL segment rather than raw non-Latin characters.
 *
 * Purely cosmetic: the detail route resolves the product by {id} alone,
 * never by slug, so this never needs to be unique and is computed fresh
 * per request in the current locale rather than stored per-locale.
 */
class ProductSlugService
{
    public function generate(Product $product, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        $name = trans_field($product->name, $locale);
        $manufacturerName = $product->manufacturer ? trans_field($product->manufacturer->name, $locale) : '';
        $conditionLabel = $product->condition ? condition_label($product->condition, $locale) : '';

        $parts = array_filter([$manufacturerName, $name, $conditionLabel], fn ($part) => $part !== '' && $part !== null);

        if ($parts === []) {
            // Nothing populated at all — fall back to the OEM number so
            // the URL segment is never empty.
            $parts = [$product->oem_number];
        }

        $slug = Str::slug(implode(' ', $parts));

        return $slug !== '' ? $slug : (string) $product->id;
    }

    /**
     * The combined {id}-{slug} route segment used in frontend.search.detail
     * URLs (Laravel has no native two-part-single-segment route syntax).
     */
    public function buildIdSlug(Product $product, ?string $locale = null): string
    {
        return $product->id . '-' . $this->generate($product, $locale);
    }
}
