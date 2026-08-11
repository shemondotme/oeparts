<?php

namespace App\Observers;

use App\Models\Page;
use App\Services\CacheService;

class PageObserver
{
    public function created(Page $page): void
    {
        $this->invalidateCache();
    }

    public function updated(Page $page): void
    {
        $this->invalidateCache();
    }

    public function deleted(Page $page): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetHomepagePageOverride();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }
}
