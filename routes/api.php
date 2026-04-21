<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — OEMHub
|--------------------------------------------------------------------------
|
| These routes are stateless JSON endpoints.
| Sprint 6 (Search), Sprint 7 (Cart), Sprint 8 (Checkout) will fill these in.
|
*/

// Health ping
Route::get('/ping', fn() => response()->json(['ok' => true]));

// VAT Validation (for real-time VIES validation in checkout)
Route::post('/validate-vat', [\App\Http\Controllers\Api\VatValidationController::class, 'validate'])
    ->name('api.validate-vat');

// Search API (Sprint 6)
Route::get('/search/autocomplete', [\App\Http\Controllers\Api\SearchController::class, 'autocomplete'])
    ->name('api.search.autocomplete');

// Cart API (Sprint 7)
Route::prefix('cart')->group(function () {
    Route::get('/summary', [\App\Http\Controllers\Api\CartController::class, 'summary'])->name('api.cart.summary');
    Route::post('/add', [\App\Http\Controllers\Api\CartController::class, 'add'])->name('api.cart.add');
    Route::put('/update/{itemId}', [\App\Http\Controllers\Api\CartController::class, 'update'])->name('api.cart.update');
    Route::delete('/remove/{itemId}', [\App\Http\Controllers\Api\CartController::class, 'remove'])->name('api.cart.remove');
    Route::post('/coupon/apply', [\App\Http\Controllers\Api\CartController::class, 'applyCoupon'])->name('api.cart.coupon.apply');
    Route::delete('/coupon/remove', [\App\Http\Controllers\Api\CartController::class, 'removeCoupon'])->name('api.cart.coupon.remove');
});

// Sections API
Route::prefix('sections')->group(function () {
    Route::get('/homepage', [\App\Http\Controllers\Api\SectionController::class, 'homepage'])->name('api.sections.homepage');
    Route::get('/landing', [\App\Http\Controllers\Api\SectionController::class, 'landing'])->name('api.sections.landing');
});

// Part Inquiry API (from homepage modal)
Route::post('/inquiry', [\App\Http\Controllers\Api\InquiryController::class, 'store'])
    ->name('api.inquiry.store');

