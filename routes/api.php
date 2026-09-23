<?php

use App\Http\Controllers\Api\B2bController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\VatValidationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — OeParts
|--------------------------------------------------------------------------
|
| Rate limiting: throttle:api (60/min per IP) applied globally.
| Auth: Sanctum optional on cart/checkout, required on b2b-request.
| Maintenance: gated the same as the web storefront (Phase 17 fix) — this
| API has no maintenance-mode awareness of its own, and its checkout
| endpoints (Api\CheckoutController::step5()) write a real Order row and
| can trigger a real gateway charge synchronously. Before this, a mobile
| client could place and pay for a real order — against a database mid
| self-update swap/migration — while the browser storefront correctly
| showed the 503 maintenance page. /ping is exempted (mirrors the web
| group's /health bypass) so uptime monitoring of the API itself still
| works during a real maintenance window.
|
*/

Route::middleware(['throttle:api', 'maintenance'])->group(function () {

    // Health ping — exempt from maintenance (MaintenanceMode::handle()'s api/ping bypass).
    Route::get('/ping', fn () => response()->json(['ok' => true]));

    // ─── Public Catalog Endpoints ────────────────────────────────────
    Route::get('/categories', [CatalogController::class, 'categories'])->name('api.categories');
    Route::get('/categories/{slug}', [CatalogController::class, 'category'])->name('api.categories.show');
    Route::get('/manufacturers', [CatalogController::class, 'manufacturers'])->name('api.manufacturers');
    Route::get('/manufacturers/{slug}', [CatalogController::class, 'manufacturer'])->name('api.manufacturers.show');
    Route::get('/car-models', [CatalogController::class, 'carModels'])->name('api.car-models');
    Route::get('/car-models/{id}', [CatalogController::class, 'carModel'])->name('api.car-models.show');
    Route::get('/parts', [CatalogController::class, 'parts'])->name('api.parts');
    Route::get('/parts/{oem}', [CatalogController::class, 'partByOem'])->name('api.parts.oem');
    Route::get('/parts/{oem}/cross-references', [CatalogController::class, 'crossReferences'])->name('api.parts.cross-references');
    Route::get('/product-details/{id}', [CatalogController::class, 'productDetails'])->name('api.product-details');
    Route::get('/shipping-methods', [ShippingController::class, 'index'])->name('api.shipping-methods');

    // ─── Public Utility Endpoints ────────────────────────────────────
    // 'vies-validation' (AppServiceProvider) was defined but never actually
    // applied to any route — only the generic 60/min throttle:api shared by
    // every public catalog endpoint covered this. Adds the VAT-specific
    // 30/min-per-user-or-IP limit on top, matching what ViesService's own
    // internal RateLimiter check already enforces deeper in the call stack.
    Route::post('/validate-vat', [VatValidationController::class, 'validate'])
        ->middleware('throttle:vies-validation')
        ->name('api.validate-vat');
    Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])->name('api.search.autocomplete');

    // ─── Sections (public, CMS-driven) ──────────────────────────────
    Route::get('/sections/homepage', [SectionController::class, 'homepage'])->name('api.sections.homepage');
    Route::get('/sections/landing', [SectionController::class, 'landing'])->name('api.sections.landing');

    // ─── Cart API (supports guest via cookie + optional Sanctum auth) ──
    // verify.same-origin: defense-in-depth CSRF mitigation for the
    // guest_token cookie identifying an unauthenticated cart — see
    // VerifySameOriginForStatefulCookies's own docblock for why this,
    // not Sanctum's stateful CSRF, is the right tool here.
    Route::middleware('verify.same-origin')->prefix('cart')->group(function () {
        Route::get('/summary', [CartController::class, 'summary'])->name('api.cart.summary');
        Route::post('/add', [CartController::class, 'add'])->name('api.cart.add');
        Route::put('/update/{itemId}', [CartController::class, 'update'])->name('api.cart.update');
        Route::delete('/remove/{itemId}', [CartController::class, 'remove'])->name('api.cart.remove');
        Route::post('/coupon/apply', [CartController::class, 'applyCoupon'])->name('api.cart.coupon.apply');
        Route::delete('/coupon/remove', [CartController::class, 'removeCoupon'])->name('api.cart.coupon.remove');
    });

    // ─── Checkout API (supports guest + optional Sanctum auth) ──────
    Route::middleware('verify.same-origin')->prefix('checkout')->group(function () {
        Route::post('/start', [CheckoutController::class, 'start'])->name('api.checkout.start');
        Route::get('/{checkoutId}', [CheckoutController::class, 'show'])->name('api.checkout.show');
        Route::post('/{checkoutId}/step1', [CheckoutController::class, 'step1'])->name('api.checkout.step1');
        Route::post('/{checkoutId}/step2', [CheckoutController::class, 'step2'])->name('api.checkout.step2');
        Route::post('/{checkoutId}/step3', [CheckoutController::class, 'step3'])->name('api.checkout.step3');
        Route::post('/{checkoutId}/step4', [CheckoutController::class, 'step4'])->name('api.checkout.step4');
        Route::post('/{checkoutId}/step5', [CheckoutController::class, 'step5'])->name('api.checkout.step5');
    });

    // ─── Authenticated-only Endpoints ───────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/b2b-request', [B2bController::class, 'store'])->name('api.b2b-request');
    });

}); // throttle:api group
