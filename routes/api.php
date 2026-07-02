<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — OeParts
|--------------------------------------------------------------------------
|
| Rate limiting: throttle:api (60/min per IP) applied globally.
| Auth: Sanctum optional on cart/checkout, required on b2b-request.
|
*/

Route::middleware('throttle:api')->group(function () {

    // Health ping
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    // ─── Public Catalog Endpoints ────────────────────────────────────
    Route::get('/categories', [\App\Http\Controllers\Api\CatalogController::class, 'categories'])->name('api.categories');
    Route::get('/categories/{slug}', [\App\Http\Controllers\Api\CatalogController::class, 'category'])->name('api.categories.show');
    Route::get('/manufacturers', [\App\Http\Controllers\Api\CatalogController::class, 'manufacturers'])->name('api.manufacturers');
    Route::get('/manufacturers/{slug}', [\App\Http\Controllers\Api\CatalogController::class, 'manufacturer'])->name('api.manufacturers.show');
    Route::get('/car-models', [\App\Http\Controllers\Api\CatalogController::class, 'carModels'])->name('api.car-models');
    Route::get('/car-models/{id}', [\App\Http\Controllers\Api\CatalogController::class, 'carModel'])->name('api.car-models.show');
    Route::get('/parts', [\App\Http\Controllers\Api\CatalogController::class, 'parts'])->name('api.parts');
    Route::get('/parts/{oem}', [\App\Http\Controllers\Api\CatalogController::class, 'partByOem'])->name('api.parts.oem');
    Route::get('/parts/{oem}/supersessions', [\App\Http\Controllers\Api\CatalogController::class, 'supersessions'])->name('api.parts.supersessions');
    Route::get('/parts/{oem}/cross-references', [\App\Http\Controllers\Api\CatalogController::class, 'crossReferences'])->name('api.parts.cross-references');
    Route::get('/product-details/{id}', [\App\Http\Controllers\Api\CatalogController::class, 'productDetails'])->name('api.product-details');
    Route::get('/shipping-methods', [\App\Http\Controllers\Api\ShippingController::class, 'index'])->name('api.shipping-methods');

    // ─── Public Utility Endpoints ────────────────────────────────────
    Route::post('/validate-vat', [\App\Http\Controllers\Api\VatValidationController::class, 'validate'])->name('api.validate-vat');
    Route::get('/search/autocomplete', [\App\Http\Controllers\Api\SearchController::class, 'autocomplete'])->name('api.search.autocomplete');
    Route::post('/inquiry', [\App\Http\Controllers\Api\InquiryController::class, 'store'])->name('api.inquiry.store');

    // ─── Sections (public, CMS-driven) ──────────────────────────────
    Route::get('/sections/homepage', [\App\Http\Controllers\Api\SectionController::class, 'homepage'])->name('api.sections.homepage');
    Route::get('/sections/landing', [\App\Http\Controllers\Api\SectionController::class, 'landing'])->name('api.sections.landing');

    // ─── Cart API (supports guest via cookie + optional Sanctum auth) ──
    Route::prefix('cart')->group(function () {
        Route::get('/summary', [\App\Http\Controllers\Api\CartController::class, 'summary'])->name('api.cart.summary');
        Route::post('/add', [\App\Http\Controllers\Api\CartController::class, 'add'])->name('api.cart.add');
        Route::put('/update/{itemId}', [\App\Http\Controllers\Api\CartController::class, 'update'])->name('api.cart.update');
        Route::delete('/remove/{itemId}', [\App\Http\Controllers\Api\CartController::class, 'remove'])->name('api.cart.remove');
        Route::post('/coupon/apply', [\App\Http\Controllers\Api\CartController::class, 'applyCoupon'])->name('api.cart.coupon.apply');
        Route::delete('/coupon/remove', [\App\Http\Controllers\Api\CartController::class, 'removeCoupon'])->name('api.cart.coupon.remove');
    });

    // ─── Checkout API (supports guest + optional Sanctum auth) ──────
    Route::prefix('checkout')->group(function () {
        Route::post('/start', [\App\Http\Controllers\Api\CheckoutController::class, 'start'])->name('api.checkout.start');
        Route::get('/{checkoutId}', [\App\Http\Controllers\Api\CheckoutController::class, 'show'])->name('api.checkout.show');
        Route::post('/{checkoutId}/step1', [\App\Http\Controllers\Api\CheckoutController::class, 'step1'])->name('api.checkout.step1');
        Route::post('/{checkoutId}/step2', [\App\Http\Controllers\Api\CheckoutController::class, 'step2'])->name('api.checkout.step2');
        Route::post('/{checkoutId}/step3', [\App\Http\Controllers\Api\CheckoutController::class, 'step3'])->name('api.checkout.step3');
        Route::post('/{checkoutId}/step4', [\App\Http\Controllers\Api\CheckoutController::class, 'step4'])->name('api.checkout.step4');
        Route::post('/{checkoutId}/step5', [\App\Http\Controllers\Api\CheckoutController::class, 'step5'])->name('api.checkout.step5');
    });

    // ─── Authenticated-only Endpoints ───────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/b2b-request', [\App\Http\Controllers\Api\B2bController::class, 'store'])->name('api.b2b-request');
    });

}); // throttle:api group
