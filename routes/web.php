<?php

use App\Http\Controllers\Frontend\AccountController;
use App\Http\Controllers\Frontend\AuthController;
/*
|--------------------------------------------------------------------------
| Web Routes — OeParts
|--------------------------------------------------------------------------
|
| Frontend routes added in Sprint 5+.
| Admin routes added in Sprint 11+.
| Installer routes in routes/installer.php (Sprint 18).
|
| IMPORTANT: The CMS catch-all slug route MUST be defined LAST within
| each language prefix group. See ARCHITECTURE.md for the exact ordering.
|
*/

use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\CarModelController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\ContactController;
use App\Http\Controllers\Frontend\CouponAjaxController;
use App\Http\Controllers\Frontend\ForgotPasswordController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\ManufacturerController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\PartInquiryController;
use App\Http\Controllers\Frontend\ResetPasswordController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\SitemapController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Root: detect browser language and redirect to /{lang}/
Route::get('/', function (Request $request) {
    $lang = detectBrowserLanguage($request);

    return redirect("/{$lang}/");
})->name('root');

// Login route for auth middleware redirect (outside language prefix)
Route::get('/login', function (Request $request) {
    // Get the current language from session or default to 'en'
    $lang = $request->session()->get('locale', 'en');

    return redirect("/{$lang}/")->with('show_auth_modal', true);
})->name('login');

// Webhook endpoints (no language prefix, CSRF exempt)
Route::post('/webhooks/airwallex', [WebhookController::class, 'handleAirwallex'])
    ->name('webhooks.airwallex')
    ->middleware('throttle:webhook')
    ->withoutMiddleware(['web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::post('/webhooks/bank-transfer-confirm', [WebhookController::class, 'handleBankTransferConfirm'])
    ->name('webhooks.bank-transfer-confirm')
    ->middleware('throttle:webhook')
    ->withoutMiddleware(['web', \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Robots.txt (dynamic)
Route::get('/robots.txt', [RobotsController::class, 'index'])
    ->name('robots.txt');

// Public health check (unauthenticated, for uptime monitoring)
Route::get('/health', HealthController::class)
    ->name('health');

/*
|--------------------------------------------------------------------------
| DEV-ONLY · Design preview routes for error pages
|--------------------------------------------------------------------------
| Only registered when APP_DEBUG=true. Lets designers/devs preview the
| rendered 429 and 503 (maintenance) views without triggering the real
| throttle or enabling maintenance mode globally. Safe to remove later.
*/
if (config('app.debug')) {
    Route::get('/_preview/429', function () {
        throw new TooManyRequestsHttpException(
            45,
            'Preview of the 429 throttle page — dev mode only.'
        );
    });

    Route::get('/_preview/maintenance', function () {
        return response()->view('errors.maintenance', [
            'message' => settings('maintenance.message', ['en' => "We're performing scheduled maintenance. We'll be back shortly."]),
            'estimatedBackAt' => settings('maintenance.estimated_back_at', now()->addHours(2)->toDateTimeString()),
            'showEstimatedTime' => true,
            'contactEmail' => settings('maintenance.contact_email', settings('general.contact_email', 'info@oeparts.lt')),
        ], 503);
    });
}

// Installer routes (Sprint 18)
require __DIR__.'/installer.php';

// Frontend routes — language-prefixed
Route::prefix('{lang}')
    ->where(['lang' => 'en|de|lt|fr|es'])
    ->middleware(['set.locale', 'ip.blocklist', 'maintenance', 'track.utm', 'handle.redirects'])
    ->group(function () {
        // Homepage
        Route::get('/', [HomeController::class, 'index'])->name('frontend.home');

        // Authentication routes (Sprint 10)
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('frontend.auth.login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('frontend.auth.register');
        Route::post('/logout', [AuthController::class, 'logout'])->name('frontend.auth.logout');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:login')->name('frontend.auth.verify-otp');
        Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:login')->name('frontend.auth.resend-otp');

        // Password reset routes
        Route::get('/reset-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('frontend.password.request');
        Route::post('/reset-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset')->name('frontend.password.email');
        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('frontend.password.reset');
        Route::post('/reset-password/update', [ResetPasswordController::class, 'reset'])->middleware('throttle:password-reset')->name('frontend.password.update');

        // Social login routes
        Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Frontend\SocialAuthController::class, 'redirect'])
            ->whereIn('provider', ['google', 'facebook'])
            ->name('social.redirect');
        Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Frontend\SocialAuthController::class, 'callback'])
            ->whereIn('provider', ['google', 'facebook'])
            ->name('social.callback');

        // Account routes (protected)
        Route::middleware(['auth:web'])->group(function () {
            Route::get('/account/dashboard', [AccountController::class, 'dashboard'])->name('frontend.account.dashboard');
            Route::get('/account/orders', [AccountController::class, 'orders'])->name('frontend.account.orders');
            Route::get('/account/orders/{order}', [AccountController::class, 'orderDetail'])->name('frontend.account.order.detail');
            Route::get('/account/orders/{order}/invoice', [AccountController::class, 'downloadInvoice'])->name('frontend.account.order.invoice');
            // Refund routes
            Route::get('/account/orders/{order}/refund', [AccountController::class, 'refundForm'])->name('frontend.account.order.refund.form');
            Route::post('/account/orders/{order}/refund', [AccountController::class, 'requestRefund'])->name('frontend.account.order.refund.submit');
            Route::get('/account/refunds', [AccountController::class, 'refunds'])->name('frontend.account.refunds');
            Route::post('/account/orders/{order}/cancel', [AccountController::class, 'cancelOrder'])->name('frontend.account.order.cancel');
            Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('frontend.account.addresses');
            Route::get('/account/addresses/create', [AccountController::class, 'addressForm'])->name('frontend.account.addresses.create');
            Route::get('/account/addresses/{address}/edit', [AccountController::class, 'addressForm'])->name('frontend.account.addresses.edit');
            Route::post('/account/addresses', [AccountController::class, 'saveAddress'])->name('frontend.account.addresses.store');
            Route::delete('/account/addresses/{address}', [AccountController::class, 'deleteAddress'])->name('frontend.account.addresses.destroy');
            Route::get('/account/settings', [AccountController::class, 'settings'])->name('frontend.account.settings');
            Route::match(['post', 'put'], '/account/settings', [AccountController::class, 'updateSettings'])->name('frontend.account.settings.update');
            Route::post('/account/settings/password', [AccountController::class, 'updatePassword'])->name('frontend.account.password.update');
            Route::post('/account/settings/notifications', [AccountController::class, 'updateNotifications'])->name('frontend.account.notifications.update');
            Route::post('/account/settings/language', [AccountController::class, 'updateLanguage'])->name('frontend.account.language.update');
            Route::delete('/account', [AccountController::class, 'destroy'])->name('frontend.account.delete');
        });

        // Search Console — empty-state landing / browse-parts entry point
        Route::get('/parts', [SearchController::class, 'console'])
            ->name('frontend.search.console');

        // OEM Search (with OEM normalization middleware)
        Route::get('/parts/{oem}', [SearchController::class, 'results'])
            ->where('oem', '[A-Z0-9\-\.\s]+')
            ->middleware('normalize.oem')
            ->name('frontend.search.results');

        // Autocomplete endpoint
        Route::get('/search/autocomplete', [SearchController::class, 'autocomplete'])
            ->middleware('throttle:' . settings('search.autocomplete_rate_limit', 60) . ',1')
            ->name('frontend.search.autocomplete');

        // Human-readable HTML sitemap (the machine-readable /sitemap.xml lives at root)
        Route::get('/sitemap', [SitemapController::class, 'index'])
            ->name('frontend.sitemap');

        // Manufacturers
        Route::get('/brands', [ManufacturerController::class, 'index'])
            ->name('frontend.manufacturer.index');
        Route::get('/brand/{manufacturer}', [ManufacturerController::class, 'show'])
            ->where('manufacturer', '[a-z0-9\-]+')
            ->name('frontend.manufacturer.show');

        // Car Models
        Route::get('/brand/{manufacturer}/models', [CarModelController::class, 'index'])
            ->where('manufacturer', '[a-z0-9\-]+')
            ->name('frontend.car-model.index');
        Route::get('/brand/{manufacturer}/{model}', [CarModelController::class, 'show'])
            ->where(['manufacturer' => '[a-z0-9\-]+', 'model' => '[a-z0-9\-]+'])
            ->name('frontend.car-model.show');

        // Cart Routes
        Route::get('/cart', [CartController::class, 'index'])->name('frontend.cart.index');
        Route::get('/cart/summary', [CartController::class, 'summary'])->name('frontend.cart.summary');
        Route::get('/cart/preview', [CartController::class, 'preview'])->name('frontend.cart.preview');
        Route::post('/cart/add', [CartController::class, 'add'])
            ->middleware('throttle:30,1')
            ->name('frontend.cart.add');
        Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('frontend.cart.remove');
        Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('frontend.cart.update');
        Route::post('/cart/merge', [CartController::class, 'merge'])->name('frontend.cart.merge');
        Route::post('/cart/coupon/apply', [CartController::class, 'applyCoupon'])->name('frontend.cart.coupon.apply');
        Route::delete('/cart/coupon/remove', [CartController::class, 'removeCoupon'])->name('frontend.cart.coupon.remove');

        // Checkout flow (Sprint 8)
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('frontend.checkout');
        Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:30,1')->name('frontend.checkout.store');
        Route::get('/checkout/thank-you/{order}', [CheckoutController::class, 'thankYou'])->name('frontend.checkout.thank-you');

        // Payment routes (Sprint 9)
        Route::prefix('checkout/payment')->group(function () {
            Route::get('/{order}', [CheckoutController::class, 'payment'])->name('frontend.checkout.payment');
            Route::get('/{order}/intent', [CheckoutController::class, 'paymentIntent'])->name('frontend.checkout.payment.intent');
            Route::post('/{order}/process', [CheckoutController::class, 'processPayment'])->name('frontend.checkout.payment.process');
            Route::get('/{order}/return', [CheckoutController::class, 'paymentReturn'])->name('frontend.checkout.payment.return');
            Route::get('/{order}/success', [CheckoutController::class, 'paymentSuccess'])->name('frontend.checkout.payment.success');
            Route::get('/{order}/failed', [CheckoutController::class, 'paymentFailed'])->name('frontend.checkout.payment.failed');
        });

        // Coupon AJAX (inside the existing lang prefix group, auth not required)
        Route::post('/coupon/apply', [CouponAjaxController::class, 'apply'])->name('frontend.coupon.apply');
        Route::delete('/coupon/remove', [CouponAjaxController::class, 'remove'])->name('frontend.coupon.remove');

        // Newsletter subscription
        Route::post('/newsletter/subscribe', [App\Http\Controllers\Frontend\NewsletterController::class, 'subscribe'])
            ->middleware('honeypot')
            ->name('frontend.newsletter.subscribe');
        Route::get('/newsletter/unsubscribe/{token}', [App\Http\Controllers\Frontend\NewsletterController::class, 'unsubscribe'])
            ->middleware('throttle:10,1')
            ->name('frontend.newsletter.unsubscribe');

        // Blog
        Route::get('/blog', [BlogController::class, 'index'])->name('frontend.blog.index');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('frontend.blog.show');

        // Contact
        Route::get('/contact', [ContactController::class, 'show'])->name('frontend.contact.show');
        Route::post('/contact/submit', [ContactController::class, 'submit'])
            ->middleware(['honeypot', 'throttle:contact'])
            ->name('frontend.contact.submit');

        // Part Inquiry (from search results page)
        Route::post('/inquiry', [PartInquiryController::class, 'store'])
            ->middleware(['honeypot', 'throttle:' . settings('part_inquiry.rate_limit_per_hour', 10) . ',1'])
            ->name('frontend.inquiry.store');

        // CMS catch-all slug — MUST be defined LAST
        Route::get('/{slug}', [PageController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('frontend.page');
    });

// ──────────────────────────────────────────────────────────────────────────
// ADMIN — additional admin routes (Filament handles the main panel)
// ──────────────────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['web'])->group(function () {

    // Export file downloads (generated by Filament CSV exports)
    Route::get('/export/download/{filename}', function (string $filename) {
        $path = storage_path('app/exports/' . basename($filename));

        if (!file_exists($path)) {
            abort(404, 'Export file not found');
        }

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    })->name('export.download')->where('filename', '[a-zA-Z0-9_\-]+\.csv$')->middleware('auth.admin');

    // ── WYSIWYG Editor (Feature 2) ───────────────────────────────────────
    Route::prefix('editor')->name('editor.')->middleware('auth.admin')->group(function () {
        Route::post('/upload-image', [\App\Http\Controllers\Admin\EditorController::class, 'uploadImage'])
            ->name('upload-image');
        Route::post('/preview-html', [\App\Http\Controllers\Admin\EditorController::class, 'previewHtml'])
            ->name('preview-html');
    });

    // ── CMS Sections & Media Picker (Features 3–5) ──────────────────────
    Route::prefix('cms')->name('cms.')->middleware('auth.admin')->group(function () {
        Route::prefix('sections')->name('sections.')->group(function () {
            Route::put('/{section}', [\App\Http\Controllers\Admin\CmsSectionController::class, 'update'])
                ->name('update');
            Route::post('/{section}/preview', [\App\Http\Controllers\Admin\CmsSectionController::class, 'preview'])
                ->name('preview');
            Route::post('/{section}/restore-version/{version}', [\App\Http\Controllers\Admin\CmsSectionController::class, 'restoreVersion'])
                ->name('restore-version');
        });

        Route::prefix('media-picker')->name('media-picker.')->group(function () {
            Route::post('/upload', [\App\Http\Controllers\Admin\MediaPickerController::class, 'upload'])
                ->name('upload');
            Route::get('/', [\App\Http\Controllers\Admin\MediaPickerController::class, 'index'])
                ->name('index');
            Route::delete('/{media}', [\App\Http\Controllers\Admin\MediaPickerController::class, 'destroy'])
                ->name('destroy');
        });
    });

    // ── Invoice PDF Download ─────────────────────────────────────────────
    Route::get('/orders/{order}/invoice', [\App\Http\Controllers\Admin\InvoiceController::class, 'download'])
        ->name('orders.invoice')
        ->middleware('auth.admin');

    // ── Backup Download ─────────────────────────────────────────────────
    Route::get('/backups/{filename}', function (string $filename) {
        $admin = auth('admin')->user();
        if (!$admin || !$admin->hasRole('super_admin')) {
            abort(403, 'Unauthorized.');
        }

        $path = storage_path('app/backups/' . basename($filename));

        if (!file_exists($path)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($path, basename($filename));
    })->name('backup.download')->where('filename', '[a-zA-Z0-9_\-]+\.zip$')->middleware(['auth.admin', 'throttle:export-download']);
});
