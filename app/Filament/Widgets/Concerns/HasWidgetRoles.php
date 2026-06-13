<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\WidgetPreferenceService;

/**
 * Role-based widget visibility driven by the WidgetPreferenceService
 * registry ('roles' key per widget). Replaces the previously duplicated
 * canView() bodies across all dashboard widgets.
 */
trait HasWidgetRoles
{
    public static function canView(): bool
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            return false;
        }

        return $admin->hasAnyRole(WidgetPreferenceService::rolesFor(static::class));
    }
}
