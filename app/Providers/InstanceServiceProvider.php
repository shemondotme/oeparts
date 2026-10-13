<?php

namespace App\Providers;

use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use App\Services\Instance\SystemGate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the SystemGate, the instance ledger and SafeMode (what this installation may do given its OE_MODE)
 * as shared instances. Things that can hold the installation still
 * (the export freeze, the import marker, the staging instance mode) add themselves to it from here as
 * they are built; until one does, the gate blocks nothing and nothing about the site changes.
 */
class InstanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemGate::class);
        $this->app->singleton(InstanceState::class);
        $this->app->singleton(SafeMode::class);
    }

    public function boot(): void
    {
        // The instance mode holds the installation still like any other source: a staging copy runs no customer
        // work, a quarantined one only what keeps it healthy. Production holds nothing, so nothing changes there.
        $this->app->make(SystemGate::class)->register($this->app->make(SafeMode::class));
    }
}
