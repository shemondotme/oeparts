<?php

namespace App\Observers;

use App\Jobs\ProcessProductImage;
use App\Models\ProductImage;

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

        try {
            ProductImage::where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->where('is_featured', true)
                ->update(['is_featured' => false]);
        } catch (\Throwable $e) {
            // Must not break the save that triggered this.
        }
    }

    private function dispatchProcessing(ProductImage $image): void
    {
        try {
            ProcessProductImage::dispatch($image->id);
        } catch (\Throwable $e) {
            // Dispatch failure must not break the upload — the gallery
            // just serves the original until reprocessed.
        }
    }
}
