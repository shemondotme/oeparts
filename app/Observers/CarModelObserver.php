<?php

namespace App\Observers;

use App\Models\CarModel;
use App\Observers\Concerns\PushesToIndexNow;
use App\Support\LocaleRegistry;

/**
 * Only products got a proactive IndexNow push — car-model pages relied
 * purely on the once-daily sitemap regeneration + organic crawl. No prior
 * observer existed for this model at all.
 */
class CarModelObserver
{
    use PushesToIndexNow;

    public function created(CarModel $carModel): void
    {
        $this->pushIfReachable($carModel);
    }

    public function updated(CarModel $carModel): void
    {
        $this->pushIfReachable($carModel);
    }

    /**
     * CarModelController::show() 404s unless BOTH the model and its
     * manufacturer are active (SitemapService::generateCarModelsSitemap()
     * enforces the same pair) — pushing a URL that 404s would be a
     * pointless (and misleading) IndexNow announcement.
     */
    protected function pushIfReachable(CarModel $carModel): void
    {
        $manufacturer = $carModel->manufacturer;

        if (! $carModel->is_active || ! $manufacturer?->is_active) {
            return;
        }

        $this->pushToIndexNow(array_map(
            fn (string $locale) => route('frontend.car-model.show', [
                'lang' => $locale,
                'manufacturer' => $manufacturer->slug,
                'model' => $carModel->slug,
            ]),
            LocaleRegistry::codes()
        ));
    }
}
