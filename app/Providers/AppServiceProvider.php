<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Coupon;
use App\Models\NewsletterCampaign;
use App\Models\Order;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Section;
use App\Models\User;
use App\Observers\AdminObserver;
use App\Observers\BlogPostObserver;
use App\Observers\CouponObserver;
use App\Observers\NewsletterCampaignObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\RefundRequestObserver;
use App\Observers\SectionObserver;
use App\Observers\UserObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\CacheService::class);
        $this->app->singleton(\App\Services\SettingsService::class);
        $this->app->singleton(\App\Services\OemNormalizerService::class);
        $this->app->singleton(\App\Services\TranslationService::class);
        $this->app->singleton(\App\Services\SearchService::class);
        $this->app->singleton(\App\Services\PreloaderService::class);

        // Register legacy Filament Tables Actions class aliases for Filament v3 compatibility
        $aliases = [
            'Filament\Actions\Action' => 'Filament\Tables\Actions\Action',
            'Filament\Actions\BulkAction' => 'Filament\Tables\Actions\BulkAction',
            'Filament\Actions\CreateAction' => 'Filament\Tables\Actions\CreateAction',
            'Filament\Actions\EditAction' => 'Filament\Tables\Actions\EditAction',
            'Filament\Actions\DeleteAction' => 'Filament\Tables\Actions\DeleteAction',
            'Filament\Actions\ViewAction' => 'Filament\Tables\Actions\ViewAction',
            'Filament\Actions\BulkActionGroup' => 'Filament\Tables\Actions\BulkActionGroup',
            'Filament\Actions\DeleteBulkAction' => 'Filament\Tables\Actions\DeleteBulkAction',
            'Filament\Actions\ActionGroup' => 'Filament\Tables\Actions\ActionGroup',
        ];

        foreach ($aliases as $original => $alias) {
            if (class_exists($original) && !class_exists($alias)) {
                class_alias($original, $alias);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set global bcmath scale to 2 decimal places.
        // NEVER call bcscale() again anywhere else in the codebase.
        bcscale(2);

        // Register custom macro for TextColumn to support legacy fontMono calls in Filament v3
        if (class_exists(\Filament\Tables\Columns\TextColumn::class)) {
            \Filament\Tables\Columns\TextColumn::macro('fontMono', function () {
                return $this->fontFamily('mono');
            });
        }

        // Same legacy shim for Infolist TextEntry — found missing while writing a
        // regression test that actually mounted a ViewRecord page (no existing
        // test had ever rendered one): every ViewRecord infolist using
        // ->fontMono() was throwing BadMethodCallException, a real, currently-
        // live 500 on every "View" click for affected resources (e.g. Orders).
        if (class_exists(\Filament\Infolists\Components\TextEntry::class)) {
            \Filament\Infolists\Components\TextEntry::macro('fontMono', function () {
                return $this->fontFamily('mono');
            });
        }

        // Register custom macro for Filament Schemas Components to support helperText calls
        if (class_exists(\Filament\Schemas\Components\Component::class)) {
            \Filament\Schemas\Components\Component::macro('helperText', function ($text) {
                return $this;
            });
        }

        // Register custom macro for Filament Tables Filters to support helperText calls
        if (class_exists(\Filament\Tables\Filters\BaseFilter::class)) {
            \Filament\Tables\Filters\BaseFilter::macro('helperText', function ($text) {
                return $this;
            });
        }

        // Register Eloquent observers for CRUD audit logging
        Order::observe(OrderObserver::class);
        Product::observe(ProductObserver::class);
        Admin::observe(AdminObserver::class);
        User::observe(UserObserver::class);
        Coupon::observe(CouponObserver::class);
        RefundRequest::observe(RefundRequestObserver::class);
        BlogPost::observe(BlogPostObserver::class);
        Section::observe(SectionObserver::class);
        NewsletterCampaign::observe(NewsletterCampaignObserver::class);

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Use the project's custom password-reset route instead of Laravel's default 'password.reset'
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return route('frontend.password.reset', [
                'lang'  => app()->getLocale() ?: 'en',
                'token' => $token,
            ]) . '?email=' . urlencode($user->getEmailForPasswordReset());
        });

        // Super admin gets all permissions via Gate::before hook (no explicit assignment needed).
        // The Gate uses the default web guard; we also check the admin guard explicitly.
        Gate::before(function ($user, $ability) {
            if ($user instanceof Admin && $user->hasRole('super_admin')) {
                return true;
            }
            $admin = auth('admin')->user();
            if ($admin && $admin->hasRole('super_admin')) {
                return true;
            }
            return null;
        });

        // Rate limiter for admin login — backup for Filament's built-in Livewire rate limiting.
        // Filament Login page already calls $this->rateLimit(5) via DanHarrin\LivewireRateLimiting,
        // but this definition is available if we need throttle middleware elsewhere.
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->input('email') . '|' . $request->ip()
            );
        });

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));

        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('export-download', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('vies-validation', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->input('email', '') . '|' . $request->ip()
            );
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by(
                $request->input('email', '') . '|' . $request->ip()
            );
        });

        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('webhook', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
    }
}
