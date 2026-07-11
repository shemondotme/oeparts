<?php

namespace App\Observers;

use App\Models\Manufacturer;
use App\Services\CacheService;
use App\Services\WidgetPreferenceService;

class ManufacturerObserver
{
    public function created(Manufacturer $manufacturer): void
    {
        $this->invalidateCache();
    }

    public function updated(Manufacturer $manufacturer): void
    {
        $this->invalidateCache();
    }

    public function deleted(Manufacturer $manufacturer): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetManufacturers();

            foreach (['manufacturer_revenue', 'manufacturing_stats'] as $widgetId) {
                WidgetPreferenceService::forgetCache($widgetId);
            }
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }
}
