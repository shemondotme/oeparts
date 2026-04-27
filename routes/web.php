<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — OEMHub
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

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\PageController;
use App\Http\Controllers\Frontend\SearchController;
use App\Http\Controllers\Frontend\ManufacturerController;
use App\Http\Controllers\Frontend\CarModelController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\ForgotPasswordController;
use App\Http\Controllers\Frontend\ResetPasswordController;
use App\Http\Controllers\Frontend\AccountController;
use App\Http\Controllers\Frontend\BlogController;
use App\Http\Controllers\Frontend\ContactController;
use App\Http\Controllers\Frontend\SitemapController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\RobotsController;

// Root: detect browser language and redirect to /{lang}/
Route::get('/', function (Request $request) {
    $lang = detectBrowserLanguage($request);
    return redirect("/{$lang}/");
});

// Login route for auth middleware redirect (outside language prefix)
Route::get('/login', function (Request $request) {
    // Get the current language from session or default to 'en'
    $lang = $request->session()->get('locale', 'en');
    return redirect("/{$lang}/")->with('show_auth_modal', true);
})->name('login');

// Webhook endpoints (no language prefix, CSRF exempt)
Route::post('/webhooks/airwallex', [WebhookController::class, 'handleAirwallex'])
    ->name('webhooks.airwallex')
    ->withoutMiddleware(['web', 'verify.csrf']);
Route::post('/webhooks/bank-transfer-confirm', [WebhookController::class, 'handleBankTransferConfirm'])
    ->name('webhooks.bank-transfer-confirm')
    ->withoutMiddleware(['web', 'verify.csrf']);

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
        throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException(
            45,
            'Preview of the 429 throttle page — dev mode only.'
        );
    });

    Route::get('/_preview/maintenance', function () {
        return response()->view('errors.maintenance', [
            'message'           => settings('maintenance.message', ['en' => "We're performing scheduled maintenance. We'll be back shortly."]),
            'estimatedBackAt'   => settings('maintenance.estimated_back_at', now()->addHours(2)->toDateTimeString()),
            'showEstimatedTime' => true,
            'contactEmail'      => settings('maintenance.contact_email', settings('general.contact_email', 'support@oemhub.eu')),
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
        Route::post('/login', [AuthController::class, 'login'])->name('frontend.auth.login');
        Route::post('/register', [AuthController::class, 'register'])->name('frontend.auth.register');
        Route::post('/logout', [AuthController::class, 'logout'])->name('frontend.auth.logout');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('frontend.auth.verify-otp');
        Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->name('frontend.auth.resend-otp');

        // 🧪 Test routes (for debugging OTP modal)
        Route::get('/test/otp-modal', function () {
            return view('test.otp-modal-test');
        })->name('test.otp-modal');

        // Password reset routes
        Route::get('/reset-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('frontend.password.request');
        Route::post('/reset-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('frontend.password.email');
        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('frontend.password.reset');
        Route::post('/reset-password/update', [ResetPasswordController::class, 'reset'])->name('frontend.password.update');

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
        Route::post('/cart/add', [CartController::class, 'add'])->name('frontend.cart.add');
        Route::delete('/cart/remove/{item}', [CartController::class, 'remove'])->name('frontend.cart.remove');
        Route::put('/cart/update/{item}', [CartController::class, 'update'])->name('frontend.cart.update');
        Route::post('/cart/merge', [CartController::class, 'merge'])->name('frontend.cart.merge');
        Route::post('/cart/coupon/apply', [CartController::class, 'applyCoupon'])->name('frontend.cart.coupon.apply');
        Route::delete('/cart/coupon/remove', [CartController::class, 'removeCoupon'])->name('frontend.cart.coupon.remove');

        // Checkout flow (Sprint 8)
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('frontend.checkout');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('frontend.checkout.store');
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
        Route::post('/coupon/apply', [\App\Http\Controllers\Frontend\CouponAjaxController::class, 'apply'])->name('frontend.coupon.apply');
        Route::delete('/coupon/remove', [\App\Http\Controllers\Frontend\CouponAjaxController::class, 'remove'])->name('frontend.coupon.remove');

        // Newsletter subscription
        Route::post('/newsletter/subscribe', [\App\Http\Controllers\Frontend\NewsletterController::class, 'subscribe'])->name('frontend.newsletter.subscribe');
        Route::get('/newsletter/unsubscribe/{token}', [\App\Http\Controllers\Frontend\NewsletterController::class, 'unsubscribe'])->name('frontend.newsletter.unsubscribe');

        // Blog
        Route::get('/blog', [BlogController::class, 'index'])->name('frontend.blog.index');
        Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('frontend.blog.show');

        // Contact
        Route::get('/contact', [ContactController::class, 'show'])->name('frontend.contact.show');
        Route::post('/contact/send-otp', [ContactController::class, 'sendOtp'])->name('frontend.contact.send-otp');
        Route::post('/contact/verify-otp', [ContactController::class, 'verifyOtp'])->name('frontend.contact.verify-otp');
        Route::post('/contact/submit', [ContactController::class, 'submit'])->name('frontend.contact.submit');

        // Part Inquiry (from search results page)
        Route::post('/inquiry', [\App\Http\Controllers\Frontend\PartInquiryController::class, 'store'])->name('frontend.inquiry.store');

        // CMS catch-all slug — MUST be defined LAST
        Route::get('/{slug}', [PageController::class, 'show'])
            ->where('slug', '[a-z0-9\-]+')
            ->name('frontend.page');
    });

    // ──────────────────────────────────────────────────────────────────────────
    // ADMIN ROUTES (Sprint 11+)
    // ──────────────────────────────────────────────────────────────────────────
    use App\Http\Controllers\Admin\AuthController as AdminAuthController;
    use App\Http\Controllers\Admin\DashboardController;
    use App\Http\Controllers\Admin\OrderController;
    use App\Http\Controllers\Admin\RefundController;
    use App\Http\Controllers\Admin\ProductController;
    use App\Http\Controllers\Admin\ManufacturerController as AdminManufacturerController;
    use App\Http\Controllers\Admin\CarModelController as AdminCarModelController;
    use App\Http\Controllers\Admin\BulkUpdateController;
    // CMS & Content (Sprint 14)
    use App\Http\Controllers\Admin\SectionController;
    use App\Http\Controllers\Admin\BlogController as AdminBlogController;
    use App\Http\Controllers\Admin\PageController as AdminPageController;
    use App\Http\Controllers\Admin\MediaController;
    use App\Http\Controllers\Admin\MediaPickerController;
    use App\Http\Controllers\Admin\MenuController;
    use App\Http\Controllers\Admin\TestimonialController;
    use App\Http\Controllers\Admin\FaqController;
    use App\Http\Controllers\Admin\NewsletterController;
    use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
    use App\Http\Controllers\Admin\InquiryController;

    Route::prefix('admin')->name('admin.')->group(function () {
        // Authentication
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // Protected admin routes
        Route::middleware(['admin.auth'])->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            Route::post('/dashboard/preferences', [DashboardController::class, 'updatePreferences'])->name('dashboard.preferences.update');
            
            // Order Management (Sprint 12)
            Route::prefix('orders')->name('orders.')->group(function () {
                Route::get('/', [OrderController::class, 'index'])->name('index');
                Route::get('/export', [OrderController::class, 'export'])->name('export');
                Route::post('/bulk-status', [OrderController::class, 'bulkStatus'])->name('bulk-status');
                // Static specific routes BEFORE /{order} wildcard (bug #24 prevention)
                Route::get('/{order}/packing-slip', [OrderController::class, 'packingSlip'])->name('packing-slip');
                Route::get('/{order}', [OrderController::class, 'show'])->name('show');
                Route::post('/{order}/status', [OrderController::class, 'updateStatus'])->name('update-status');
                Route::post('/{order}/note', [OrderController::class, 'addNote'])->name('add-note');
                Route::post('/{order}/tracking', [OrderController::class, 'updateTracking'])->name('update-tracking');
            });

            // Refund Management (Sprint 12)
            Route::prefix('refunds')->name('refunds.')->group(function () {
                Route::get('/', [RefundController::class, 'index'])->name('index');
                Route::get('/export', [RefundController::class, 'export'])->name('export');
                Route::get('/{refund}', [RefundController::class, 'show'])->name('show');
                Route::post('/{refund}/status', [RefundController::class, 'updateStatus'])->name('update-status');
                Route::post('/{refund}/process', [RefundController::class, 'process'])->name('process');
            });

            // Catalog Management (Sprint 13)
            Route::prefix('catalog')->name('catalog.')->group(function () {
                // Products
                Route::prefix('products')->name('products.')->group(function () {
                    Route::get('/', [ProductController::class, 'index'])->name('index');
                    Route::get('/create', [ProductController::class, 'create'])->name('create');
                    Route::post('/', [ProductController::class, 'store'])->name('store');
                    Route::get('/import', [ProductController::class, 'importForm'])->name('import');
                    Route::post('/import', [ProductController::class, 'import'])->name('import.process');
                    Route::get('/import/template', [ProductController::class, 'csvTemplate'])->name('import.template');
                    Route::get('/{product}', [ProductController::class, 'show'])->name('show');
                    Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
                    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
                    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
                    Route::post('/{product}/inline', [ProductController::class, 'inlineUpdate'])->name('inline-update');
                    
                    // Bulk operations
                    Route::post('/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('bulk-destroy');
                    Route::post('/bulk-activate', [ProductController::class, 'bulkActivate'])->name('bulk-activate');
                    Route::post('/bulk-deactivate', [ProductController::class, 'bulkDeactivate'])->name('bulk-deactivate');
                });

                // Manufacturers
                Route::prefix('manufacturers')->name('manufacturers.')->group(function () {
                    Route::get('/', [AdminManufacturerController::class, 'index'])->name('index');
                    Route::get('/create', [AdminManufacturerController::class, 'create'])->name('create');
                    Route::post('/', [AdminManufacturerController::class, 'store'])->name('store');
                    Route::get('/{manufacturer}', [AdminManufacturerController::class, 'show'])->name('show');
                    Route::get('/{manufacturer}/edit', [AdminManufacturerController::class, 'edit'])->name('edit');
                    Route::put('/{manufacturer}', [AdminManufacturerController::class, 'update'])->name('update');
                    Route::delete('/{manufacturer}', [AdminManufacturerController::class, 'destroy'])->name('destroy');
                });

                // Car Models
                Route::prefix('car-models')->name('car-models.')->group(function () {
                    Route::get('/', [AdminCarModelController::class, 'index'])->name('index');
                    Route::get('/create', [AdminCarModelController::class, 'create'])->name('create');
                    Route::post('/', [AdminCarModelController::class, 'store'])->name('store');
                    Route::post('/bulk-activate', [AdminCarModelController::class, 'bulkActivate'])->name('bulk-activate');
                    Route::post('/bulk-deactivate', [AdminCarModelController::class, 'bulkDeactivate'])->name('bulk-deactivate');
                    Route::get('/{car_model}', [AdminCarModelController::class, 'show'])->name('show');
                    Route::get('/{car_model}/edit', [AdminCarModelController::class, 'edit'])->name('edit');
                    Route::put('/{car_model}', [AdminCarModelController::class, 'update'])->name('update');
                    Route::delete('/{car_model}', [AdminCarModelController::class, 'destroy'])->name('destroy');
                });

                // Bulk Update (Sprint 13)
                Route::prefix('bulk-update')->name('bulk-update.')->group(function () {
                    Route::get('/', [BulkUpdateController::class, 'index'])->name('index');
                    Route::get('/preview', [BulkUpdateController::class, 'preview'])->name('preview');
                    Route::post('/execute', [BulkUpdateController::class, 'execute'])->name('execute');
                    Route::get('/logs', [BulkUpdateController::class, 'logs'])->name('logs');
                    Route::get('/logs/{log}', [BulkUpdateController::class, 'showLog'])->name('logs.show');
                });
            });
            
            // CMS & Content Management (Sprint 14)
            Route::prefix('cms')->name('cms.')->group(function () {
                // Sections
                Route::prefix('sections')->name('sections.')->group(function () {
                    Route::get('/', [SectionController::class, 'index'])->name('index');
                    Route::get('/create', [SectionController::class, 'create'])->name('create');
                    Route::post('/', [SectionController::class, 'store'])->name('store');
                    Route::get('/{section}', [SectionController::class, 'show'])->name('show');
                    Route::get('/{section}/edit', [SectionController::class, 'edit'])->name('edit');
                    Route::put('/{section}', [SectionController::class, 'update'])->name('update');
                    Route::delete('/{section}', [SectionController::class, 'destroy'])->name('destroy');
                    Route::post('/reorder', [SectionController::class, 'reorder'])->name('reorder');
                    Route::post('/{section}/restore/{version}', [SectionController::class, 'restoreVersion'])->name('restore-version');
                    Route::post('/{section}/preview', [SectionController::class, 'preview'])->name('preview');
                });

                // Media Picker
                Route::prefix('media-picker')->name('media-picker.')->group(function () {
                    Route::get('/', [MediaPickerController::class, 'index'])->name('index');
                    Route::post('/upload', [MediaPickerController::class, 'upload'])->name('upload');
                    Route::delete('/{media}', [MediaPickerController::class, 'destroy'])->name('destroy');
                });

                // Blog
                Route::prefix('blog')->name('blog.')->group(function () {
                    Route::get('/', [AdminBlogController::class, 'index'])->name('index');
                    Route::get('/create', [AdminBlogController::class, 'create'])->name('create');
                    Route::post('/', [AdminBlogController::class, 'store'])->name('store');
                    Route::get('/{blog}', [AdminBlogController::class, 'show'])->name('show');
                    Route::get('/{blog}/edit', [AdminBlogController::class, 'edit'])->name('edit');
                    Route::put('/{blog}', [AdminBlogController::class, 'update'])->name('update');
                    Route::delete('/{blog}', [AdminBlogController::class, 'destroy'])->name('destroy');
                });

                // Pages
                Route::prefix('pages')->name('pages.')->group(function () {
                    Route::get('/', [AdminPageController::class, 'index'])->name('index');
                    Route::get('/create', [AdminPageController::class, 'create'])->name('create');
                    Route::post('/', [AdminPageController::class, 'store'])->name('store');
                    Route::get('/{page}', [AdminPageController::class, 'show'])->name('show');
                    Route::get('/{page}/edit', [AdminPageController::class, 'edit'])->name('edit');
                    Route::put('/{page}', [AdminPageController::class, 'update'])->name('update');
                    Route::delete('/{page}', [AdminPageController::class, 'destroy'])->name('destroy');
                });

                // Media
                Route::prefix('media')->name('media.')->group(function () {
                    Route::get('/', [MediaController::class, 'index'])->name('index');
                    Route::get('/create', [MediaController::class, 'create'])->name('create');
                    Route::post('/', [MediaController::class, 'store'])->name('store');
                    Route::get('/{media}', [MediaController::class, 'show'])->name('show');
                    Route::get('/{media}/edit', [MediaController::class, 'edit'])->name('edit');
                    Route::put('/{media}', [MediaController::class, 'update'])->name('update');
                    Route::delete('/{media}', [MediaController::class, 'destroy'])->name('destroy');
                });

                // Menus
                Route::prefix('menus')->name('menus.')->group(function () {
                    Route::get('/', [MenuController::class, 'index'])->name('index');
                    Route::get('/create', [MenuController::class, 'create'])->name('create');
                    Route::post('/', [MenuController::class, 'store'])->name('store');
                    Route::get('/{menu}', [MenuController::class, 'show'])->name('show');
                    Route::get('/{menu}/edit', [MenuController::class, 'edit'])->name('edit');
                    Route::put('/{menu}', [MenuController::class, 'update'])->name('update');
                    Route::delete('/{menu}', [MenuController::class, 'destroy'])->name('destroy');
                    Route::post('/{menu}/items', [MenuController::class, 'storeItem'])->name('items.store');
                    Route::put('/{menu}/items/{item}', [MenuController::class, 'updateItem'])->name('items.update');
                    Route::delete('/{menu}/items/{item}', [MenuController::class, 'destroyItem'])->name('items.destroy');
                    Route::post('/{menu}/reorder', [MenuController::class, 'reorder'])->name('reorder');
                });

                // Testimonials
                Route::prefix('testimonials')->name('testimonials.')->group(function () {
                    Route::get('/', [TestimonialController::class, 'index'])->name('index');
                    Route::get('/create', [TestimonialController::class, 'create'])->name('create');
                    Route::post('/', [TestimonialController::class, 'store'])->name('store');
                    Route::get('/{testimonial}', [TestimonialController::class, 'show'])->name('show');
                    Route::get('/{testimonial}/edit', [TestimonialController::class, 'edit'])->name('edit');
                    Route::put('/{testimonial}', [TestimonialController::class, 'update'])->name('update');
                    Route::delete('/{testimonial}', [TestimonialController::class, 'destroy'])->name('destroy');
                    Route::post('/{testimonial}/toggle-approval', [TestimonialController::class, 'toggleApproval'])->name('toggle-approval');
                    Route::post('/{testimonial}/toggle-featured', [TestimonialController::class, 'toggleFeatured'])->name('toggle-featured');
                });

                // FAQs
                Route::prefix('faqs')->name('faqs.')->group(function () {
                    Route::get('/', [FaqController::class, 'index'])->name('index');
                    Route::get('/create', [FaqController::class, 'create'])->name('create');
                    Route::post('/', [FaqController::class, 'store'])->name('store');
                    Route::get('/{faq}', [FaqController::class, 'show'])->name('show');
                    Route::get('/{faq}/edit', [FaqController::class, 'edit'])->name('edit');
                    Route::put('/{faq}', [FaqController::class, 'update'])->name('update');
                    Route::delete('/{faq}', [FaqController::class, 'destroy'])->name('destroy');
                    Route::post('/reorder', [FaqController::class, 'reorder'])->name('reorder');
                });

                // Newsletter
                Route::prefix('newsletter')->name('newsletter.')->group(function () {
                    Route::get('/', [NewsletterController::class, 'index'])->name('index');
                    Route::get('/create', [NewsletterController::class, 'create'])->name('create');
                    Route::get('/export', [NewsletterController::class, 'export'])->name('export');
                    Route::post('/', [NewsletterController::class, 'store'])->name('store');
                    Route::get('/{newsletter}', [NewsletterController::class, 'show'])->name('show');
                    Route::get('/{newsletter}/edit', [NewsletterController::class, 'edit'])->name('edit');
                    Route::put('/{newsletter}', [NewsletterController::class, 'update'])->name('update');
                    Route::delete('/{newsletter}', [NewsletterController::class, 'destroy'])->name('destroy');
                    Route::post('/{newsletter}/toggle-status', [NewsletterController::class, 'toggleStatus'])->name('toggle-status');
                });

                // Contact Messages
                Route::prefix('contact')->name('contact.')->group(function () {
                    Route::get('/', [AdminContactMessageController::class, 'index'])->name('index');
                    Route::get('/export', [AdminContactMessageController::class, 'export'])->name('export');
                    Route::get('/{contact}', [AdminContactMessageController::class, 'show'])->name('show');
                    Route::post('/{contact}/status', [AdminContactMessageController::class, 'updateStatus'])->name('update-status');
                    Route::post('/{contact}/reply', [AdminContactMessageController::class, 'addReply'])->name('add-reply');
                    Route::delete('/{contact}', [AdminContactMessageController::class, 'destroy'])->name('destroy');
                });

                // Part Inquiries (Kanban)
                Route::prefix('inquiries')->name('inquiries.')->group(function () {
                    Route::get('/', [InquiryController::class, 'index'])->name('index');
                    Route::get('/export', [InquiryController::class, 'export'])->name('export');
                    Route::get('/{inquiry}', [InquiryController::class, 'show'])->name('show');
                    Route::post('/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('update-status');
                    Route::post('/{inquiry}/reply', [InquiryController::class, 'addReply'])->name('add-reply');
                    Route::delete('/{inquiry}', [InquiryController::class, 'destroy'])->name('destroy');
                    Route::post('/{inquiry}/move', [InquiryController::class, 'move'])->name('move');
                });
            });
            
                // Settings Management (Sprint 15)
                Route::prefix('settings')->name('settings.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Http\Controllers\Admin\SettingsController::class, 'create'])->name('create');
                    Route::post('/', [\App\Http\Controllers\Admin\SettingsController::class, 'store'])->name('store');
                    Route::get('/{group}/edit', [\App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('edit');
                    Route::put('/{group}', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('update');
                    Route::delete('/{group}/{key}', [\App\Http\Controllers\Admin\SettingsController::class, 'destroy'])->name('destroy');
                    
                    // Preloader Settings (dedicated UI)
                    Route::get('/preloader/config', [\App\Http\Controllers\Admin\PreloaderSettingsController::class, 'show'])->name('preloader');
                    Route::put('/preloader/config', [\App\Http\Controllers\Admin\PreloaderSettingsController::class, 'update'])->name('preloader.update');
                });

                // Reports Management (Sprint 15)
                Route::prefix('reports')->name('reports.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('index');
                    Route::get('/sales', [\App\Http\Controllers\Admin\ReportController::class, 'sales'])->name('sales');
                    Route::get('/customers', [\App\Http\Controllers\Admin\ReportController::class, 'customers'])->name('customers');
                    Route::get('/search', [\App\Http\Controllers\Admin\ReportController::class, 'search'])->name('search');
                    Route::get('/checkout', [\App\Http\Controllers\Admin\ReportController::class, 'checkout'])->name('checkout');
                    Route::get('/export', [\App\Http\Controllers\Admin\ReportController::class, 'export'])->name('export');
                });

                // Health Management (Sprint 15)
                Route::prefix('health')->name('health.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\HealthController::class, 'index'])->name('index');
                    Route::post('/run-check', [\App\Http\Controllers\Admin\HealthController::class, 'runCheck'])->name('run-check');
                    Route::get('/export', [\App\Http\Controllers\Admin\HealthController::class, 'export'])->name('export');
                });

                // Logs Management
                Route::prefix('logs')->name('logs.')->group(function () {
                    Route::get('/activity', [\App\Http\Controllers\Admin\LogController::class, 'activityLogs'])->name('activity');
                    Route::get('/login', [\App\Http\Controllers\Admin\LogController::class, 'loginLogs'])->name('login');
                    Route::get('/cron', [\App\Http\Controllers\Admin\LogController::class, 'cronLogs'])->name('cron');
                    Route::get('/email', [\App\Http\Controllers\Admin\LogController::class, 'emailLogs'])->name('email');
                });

                // IP Blocklist Management
                Route::prefix('ip-blocklist')->name('settings.ip-blocklist.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\IpBlocklistController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Http\Controllers\Admin\IpBlocklistController::class, 'create'])->name('create');
                    Route::post('/', [\App\Http\Controllers\Admin\IpBlocklistController::class, 'store'])->name('store');
                    Route::post('/{ipBlock}/toggle', [\App\Http\Controllers\Admin\IpBlocklistController::class, 'toggle'])->name('toggle');
                    Route::delete('/{ipBlock}', [\App\Http\Controllers\Admin\IpBlocklistController::class, 'destroy'])->name('destroy');
                });

                // URL Redirects Management
                Route::prefix('redirects')->name('settings.redirects.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\RedirectController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Http\Controllers\Admin\RedirectController::class, 'create'])->name('create');
                    Route::post('/', [\App\Http\Controllers\Admin\RedirectController::class, 'store'])->name('store');
                    Route::get('/{redirect}/edit', [\App\Http\Controllers\Admin\RedirectController::class, 'edit'])->name('edit');
                    Route::put('/{redirect}', [\App\Http\Controllers\Admin\RedirectController::class, 'update'])->name('update');
                    Route::post('/{redirect}/toggle', [\App\Http\Controllers\Admin\RedirectController::class, 'toggle'])->name('toggle');
                    Route::delete('/{redirect}', [\App\Http\Controllers\Admin\RedirectController::class, 'destroy'])->name('destroy');
                });

                // Marketing - Abandoned Carts
                Route::prefix('abandoned-carts')->name('marketing.abandoned-carts.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'index'])->name('index');
                    Route::get('/export', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'export'])->name('export');
                    Route::post('/{cart}/send-recovery', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'sendRecovery'])->name('send-recovery');
                });

                // Translation Management (Sprint 15)
                // IMPORTANT: all static routes must appear BEFORE wildcard routes
                Route::prefix('translations')->name('translations.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\TranslationController::class, 'index'])->name('index');
                    Route::post('/', [\App\Http\Controllers\Admin\TranslationController::class, 'store'])->name('store');
                    // Static specific routes BEFORE /{group} wildcard (bug #24 prevention)
                    Route::get('/create', [\App\Http\Controllers\Admin\TranslationController::class, 'create'])->name('create');
                    Route::get('/scan', [\App\Http\Controllers\Admin\TranslationController::class, 'scan'])->name('scan');
                    Route::post('/scan', [\App\Http\Controllers\Admin\TranslationController::class, 'addScanned'])->name('scan.process');
                    Route::get('/import', [\App\Http\Controllers\Admin\TranslationController::class, 'importForm'])->name('import');
                    Route::post('/import', [\App\Http\Controllers\Admin\TranslationController::class, 'import'])->name('import.process');
                    Route::get('/export', [\App\Http\Controllers\Admin\TranslationController::class, 'export'])->name('export');
                    Route::get('/export/{group}', [\App\Http\Controllers\Admin\TranslationController::class, 'exportGroup'])->name('export.group');
                    Route::get('/languages', [\App\Http\Controllers\Admin\TranslationController::class, 'languages'])->name('languages');
                    Route::post('/languages', [\App\Http\Controllers\Admin\TranslationController::class, 'addLanguage'])->name('languages.add');
                    Route::put('/languages/{language}', [\App\Http\Controllers\Admin\TranslationController::class, 'updateLanguage'])->name('languages.update');
                    // Wildcard routes LAST
                    Route::get('/{group}', [\App\Http\Controllers\Admin\TranslationController::class, 'group'])->name('group');
                    Route::put('/{group}/bulk', [\App\Http\Controllers\Admin\TranslationController::class, 'bulkUpdate'])->name('bulkUpdate');
                    Route::get('/{translation}/edit', [\App\Http\Controllers\Admin\TranslationController::class, 'edit'])->name('edit');
                    Route::put('/{translation}', [\App\Http\Controllers\Admin\TranslationController::class, 'update'])->name('update');
                    Route::delete('/{translation}', [\App\Http\Controllers\Admin\TranslationController::class, 'destroy'])->name('destroy');
                });

            // Coupons
                Route::prefix('coupons')->name('coupons.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\CouponController::class, 'index'])->name('index');
                    Route::get('/create', [\App\Http\Controllers\Admin\CouponController::class, 'create'])->name('create');
                    Route::post('/', [\App\Http\Controllers\Admin\CouponController::class, 'store'])->name('store');
                    Route::patch('/{coupon}/toggle', [\App\Http\Controllers\Admin\CouponController::class, 'toggle'])->name('toggle');
                    Route::get('/{coupon}', [\App\Http\Controllers\Admin\CouponController::class, 'show'])->name('show');
                    Route::get('/{coupon}/edit', [\App\Http\Controllers\Admin\CouponController::class, 'edit'])->name('edit');
                    Route::put('/{coupon}', [\App\Http\Controllers\Admin\CouponController::class, 'update'])->name('update');
                    Route::delete('/{coupon}', [\App\Http\Controllers\Admin\CouponController::class, 'destroy'])->name('destroy');
                });

                // Customers
                Route::prefix('customers')->name('customers.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('index');
                    Route::get('/{user}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('show');
                    Route::patch('/{user}/toggle-active', [\App\Http\Controllers\Admin\CustomerController::class, 'toggleActive'])->name('toggle-active');
                });

                // Editor API routes (for rich text editor, media uploads, previews)
                Route::prefix('editor-api')->name('editor.')->middleware('auth:admin')->group(function () {
                    Route::post('/upload-image', [\App\Http\Controllers\Admin\EditorController::class, 'uploadImage'])->name('upload-image');
                    Route::post('/preview-html', [\App\Http\Controllers\Admin\EditorController::class, 'previewHtml'])->name('preview-html');
                });

            // Additional admin routes will be added in later sprints
        });
    });
