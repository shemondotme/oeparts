<?php

namespace App\Observers;

use App\Models\CarModel;
use App\Observers\Concerns\PushesToIndexNow;
use App\Observers\Concerns\RedirectsOnSlugChange;
use App\Support\LocaleRegistry;

/**
 * Only products got a proactive IndexNow push — car-model pages relied
 * purely on the once-daily sitemap regeneration + organic crawl. No prior
 * observer existed for this model at all.
 */
class CarModelObserver
{
    use PushesToIndexNow, RedirectsOnSlugChange;

    public function created(CarModel $carModel): void
    {
        $this->pushIfReachable($carModel);
    }

    public function updated(CarModel $carModel): void
    {
        // The old URL was only live if the model (and its brand) was reachable.
        if ($carModel->wasChanged('slug') && $carModel->getOriginal('is_active') && $carModel->manufacturer?->is_active) {
            $brandSlug = (string) $carModel->manufacturer->slug;

            $this->redirectOldSlug(
                (string) $carModel->getOriginal('slug'),
                (string) $carModel->slug,
                fn (string $locale, string $slug) => "{$locale}/brand/{$brandSlug}/{$slug}"
            );
        }

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
