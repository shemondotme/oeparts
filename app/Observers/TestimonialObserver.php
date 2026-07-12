<?php

namespace App\Observers;

use App\Models\Testimonial;
use App\Services\CacheService;

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
        }
    }
}
