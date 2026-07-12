<?php

namespace App\Observers;

use App\Models\Condition;
use App\Services\CacheService;

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
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }
}
