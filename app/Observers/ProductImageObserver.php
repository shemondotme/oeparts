<?php

namespace App\Observers;

use App\Jobs\ProcessProductImage;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;

class ProductImageObserver
{
    public function created(ProductImage $image): void
    {
        $this->enforceSingleFeatured($image);
        $this->dispatchProcessing($image);
    }

    public function updated(ProductImage $image): void
    {
        if ($image->wasChanged('is_featured')) {
            $this->enforceSingleFeatured($image);
        }
    }

    /**
     * "Exactly one featured image per product" is enforced here — the
     * single place this invariant lives — rather than in the Filament UI
     * layer, so it holds regardless of how a row gets created (admin
     * form, factory, a future bulk import) instead of only when a
     * specific form widget's callback happens to fire.
     */
    private function enforceSingleFeatured(ProductImage $image): void
    {
        if (! $image->is_featured) {
            return;
        }

        // Captured once — referencing $image->product_id a second time in
        // the catch below would otherwise double the pre-existing PHPStan
        // baseline count for this dynamic-property access.
        $productId = $image->product_id;

        try {
            ProductImage::where('product_id', $productId)
                ->where('id', '!=', $image->id)
                ->where('is_featured', true)
                ->update(['is_featured' => false]);
        } catch (\Throwable $e) {
            // Must not break the save that triggered this — but a failure
            // here can leave MORE THAN ONE image marked featured for the
            // same product, a real data-integrity issue worth tracing.
            Log::warning("ProductImageObserver: failed to enforce single-featured-image for product #{$productId}: ".$e->getMessage());
        }
    }

    private function dispatchProcessing(ProductImage $image): void
    {
        try {
            ProcessProductImage::dispatch($image->id);
        } catch (\Throwable $e) {
            // Dispatch failure must not break the upload — the gallery
            // just serves the original until reprocessed.
            Log::warning("ProductImageObserver: failed to dispatch ProcessProductImage for image #{$image->id}: ".$e->getMessage());
        }
    }
}
