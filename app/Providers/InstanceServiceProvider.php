<?php

namespace App\Providers;

use App\Services\Instance\SystemGate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the SystemGate as the one shared instance. Things that can hold the installation still
 * (the export freeze, the import marker, the staging instance mode) add themselves to it from here as
 * they are built; until one does, the gate blocks nothing and nothing about the site changes.
 */
class InstanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemGate::class);
    }
}
