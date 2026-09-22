<?php

namespace App\Observers;

use App\Models\Testimonial;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class TestimonialObserver
{
    public function created(Testimonial $testimonial): void
    {
        $this->invalidateCache();
    }

    public function updated(Testimonial $testimonial): void
    {
        $this->invalidateCache();
    }

    public function deleted(Testimonial $testimonial): void
    {
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            app(CacheService::class)->forgetTestimonials();
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
            Log::warning('TestimonialObserver: cache invalidation failed: '.$e->getMessage());
        }
    }
}
