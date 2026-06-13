<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\AdminCacheService;
use App\Services\WidgetPreferenceService;

/**
 * Widget data caching through AdminCacheService.
 *
 * Contract: the closure must return a PLAIN ARRAY (scalars/arrays only) —
 * never Filament Stat objects, models, or closures, which are not safely
 * serializable. Build presentation objects from the cached array afterwards.
 *
 * Key shape: {widget_id}:p{period or '-'} — data is identical across admins
 * and roles (only visibility differs), so keys are NOT per-admin. Bounded
 * cardinality: ≤ 26 widgets × 5 periods.
 */
trait InteractsWithDashboardCache
{
    protected function cachedWidgetData(callable $callback): array
    {
        $service = app(WidgetPreferenceService::class);

        $id = $service->getWidgetId(static::class) ?? class_basename(static::class);
        $period = property_exists($this, 'period') ? $this->period : '-';

        return AdminCacheService::dashboard(
            "{$id}:p{$period}",
            $callback,
            WidgetPreferenceService::ttlFor(static::class),
        );
    }

    protected function cachedHealthData(string $suffix, callable $callback): array
    {
        $service = app(WidgetPreferenceService::class);

        $id = $service->getWidgetId(static::class) ?? class_basename(static::class);

        return AdminCacheService::health(
            "{$id}:{$suffix}",
            $callback,
            WidgetPreferenceService::ttlFor(static::class),
        );
    }
}
