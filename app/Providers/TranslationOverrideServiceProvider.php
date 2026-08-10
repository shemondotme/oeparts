<?php

namespace App\Providers;

use App\Support\DatabaseTranslationLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

/**
 * Wraps Laravel's file-based translation loader with a DB-override layer
 * (see DatabaseTranslationLoader) so TranslationResource's admin UI — until
 * now completely disconnected from every __()/trans() call in the app —
 * actually changes site copy.
 */
class TranslationOverrideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('translation.loader', fn (Loader $files) => new DatabaseTranslationLoader($files));
    }
}
