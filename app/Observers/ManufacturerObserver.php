<?php

namespace App\Observers;

use App\Models\Manufacturer;
use App\Observers\Concerns\PushesToIndexNow;
use App\Services\CacheService;
use App\Services\WidgetPreferenceService;
use App\Support\LocaleRegistry;

class ManufacturerObserver
{
    use PushesToIndexNow;

    public function created(Manufacturer $manufacturer): void
    {
        $this->invalidateCache();
        $this->pushIfActive($manufacturer);
    }

    public function updated(Manufacturer $manufacturer): void
    {
        $this->invalidateCache();
        $this->pushIfActive($manufacturer);
    }

    public function deleted(Manufacturer $manufacturer): void
    {
        $this->invalidateCache();
    }

    /**
     * Only products got a proactive IndexNow push — brand pages relied
     * purely on the once-daily sitemap regeneration + organic crawl.
     */
    protected function pushIfActive(Manufacturer $manufacturer): void
    {
        if (! $manufacturer->is_active) {
            return;
        }

        $this->pushToIndexNow(array_map(
            fn (string $locale) => route('frontend.manufacturer.show', ['lang' => $locale, 'manufacturer' => $manufacturer->slug]),
            LocaleRegistry::codes()
        ));
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetManufacturers();
            app(CacheService::class)->forgetSearchConsoleStats();

            foreach (['manufacturer_revenue', 'manufacturing_stats'] as $widgetId) {
                WidgetPreferenceService::forgetCache($widgetId);
            }
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }
}
