<?php

namespace App\Observers;

use App\Models\Condition;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class ConditionObserver
{
    public function created(Condition $condition): void
    {
        $this->invalidateCache();
    }

    public function updated(Condition $condition): void
    {
        $this->invalidateCache();
    }

    public function deleted(Condition $condition): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetActiveConditions();
            app(CacheService::class)->forgetConditionsBySlug();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
            Log::warning('ConditionObserver: cache invalidation failed: '.$e->getMessage());
        }
    }
}
