<?php

namespace App\Filament\Clusters\Concerns;

/**
 * Point a cluster's sidebar nav link straight at its first accessible child.
 *
 * By default Filament links a cluster to its own index URL, whose mount()
 * does a redirect() to the first child. Under SPA mode (wire:navigate) that
 * mid-mount redirect isn't followed and the click dead-ends on the dashboard.
 * Linking directly to the child avoids the redirect entirely, so the nav item
 * works under SPA. The active-state pattern still matches all cluster routes.
 */
trait RedirectsNavigationToFirstChild
{
    public static function getNavigationUrl(): string
    {
        $first = collect(static::getClusteredComponents())
            ->filter(fn (string $component): bool => $component::canAccess())
            ->sortBy(fn (string $component): int => $component::getNavigationSort() ?? 0)
            ->first();

        return $first ? $first::getUrl() : parent::getNavigationUrl();
    }
}
