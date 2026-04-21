<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
    }
}
