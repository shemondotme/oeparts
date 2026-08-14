<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\ProductReviewRequest;
use App\Models\Product;
use App\Models\Review;

class ProductReviewController extends Controller
{
    /**
     * Public review submission — kept out of SearchController (already the
     * largest frontend controller) since this is a distinct write concern,
     * not a search/detail-rendering one.
     *
     * Route: POST /{lang}/parts/{oem}/{idSlug}/review
     */
    public function store(ProductReviewRequest $request, string $lang, string $oem, string $idSlug)
    {
        if (! filter_var(settings('pdp.show_reviews', true), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        $id = (int) strtok($idSlug, '-');
        $product = Product::where('is_active', true)->findOrFail($id);

        Review::create([
            'product_id' => $product->id,
            'reviewer_name' => $request->validated('reviewer_name'),
            'title' => $request->validated('title'),
            'comment' => $request->validated('comment'),
            'rating' => $request->validated('rating'),
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', __('search.review_submitted_pending'));
    }
}
