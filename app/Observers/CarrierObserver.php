<?php

namespace App\Observers;

use App\Models\Carrier;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

/**
 * The storefront lists the enabled carriers (cart, footer badge, shipping
 * section, product FAQ). Drop the cached list whenever one changes so an
 * enable/disable/rename/reorder in the admin shows up straight away.
 */
class CarrierObserver
{
    public function saved(Carrier $carrier): void
    {
        $this->invalidateCache();
    }

    public function deleted(Carrier $carrier): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetActiveCarriers();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
            Log::warning('CarrierObserver: cache invalidation failed: '.$e->getMessage());
        }
    }
}
