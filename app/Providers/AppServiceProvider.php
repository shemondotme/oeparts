<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Filament 5.x moved table actions from Tables\Actions to Actions namespace.
        // These aliases preserve backward compatibility for existing resource files.
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set global bcmath scale to 2 decimal places.
        // NEVER call bcscale() again anywhere else in the codebase.
        bcscale(2);

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
    }
}
