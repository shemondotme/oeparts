<?php

namespace App\Observers;

use App\Models\Faq;
use App\Services\CacheService;

class FaqObserver
{
    public function created(Faq $faq): void
    {
        $this->invalidateCache();
    }

    public function updated(Faq $faq): void
    {
        $this->invalidateCache();
    }

    public function deleted(Faq $faq): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetFaqs();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }
}
