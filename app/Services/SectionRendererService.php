<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Enums\SectionLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * SectionRendererService — loads and renders homepage sections.
 *
 * Sections are cached via CacheService. Each section type maps to a
 * Blade component in resources/views/components/sections/{type}.blade.php.
 *
 * Relation data (testimonials, faqs, blog posts, manufacturers) is loaded
 * once per request and passed to the relevant section components.
 */
class SectionRendererService
{
    public function __construct(private CacheService $cache) {}

    /**
     * Return active sections for the given location, in sort order.
     * Result is cached; use CacheService::forgetSections() to invalidate.
     */
    public function getSections(string $location): Collection
    {
        return $this->cache->rememberSection($location, function () use ($location) {
            return Section::where('location', $location)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Build the data bag passed to section components.
     * Heavy queries (testimonials, faqs, etc.) are only executed when
     * the corresponding section type is present in the active sections.
     */
    public function buildSectionData(Collection $sections): array
    {
        $types = $sections->pluck('type')->unique()->toArray();

        $data = [];

        if (in_array('testimonials', $types)) {
            $data['testimonials'] = $this->loadTestimonials();
        }

        if (in_array('faqs', $types)) {
            $data['faqs'] = $this->loadFaqs();
        }

        if (in_array('blog_preview', $types)) {
            $data['blog_posts'] = $this->loadBlogPosts();
        }

        if (in_array('featured_brands', $types)) {
            $data['manufacturers'] = $this->loadManufacturers();
        }

        return $data;
    }

    // ── Private data loaders ──────────────────────────────────────────────────

    private function loadTestimonials(): Collection
    {
        try {
            return Testimonial::where('is_active', true)
                ->orderBy('sort_order')
                ->limit(6)
                ->get();
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load testimonials', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function loadFaqs(): Collection
    {
        try {
            return Faq::where('is_active', true)
                ->orderBy('sort_order')
                ->limit(8)
                ->get();
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load faqs', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function loadBlogPosts(): Collection
    {
        try {
            return BlogPost::where('status', 'published')
                ->with(['category', 'author'])
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->limit(6)
                ->get();
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load blog posts', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function loadManufacturers(): Collection
    {
        try {
            return app(CacheService::class)->rememberManufacturers(function () {
                return Manufacturer::where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit(12)
                    ->get();
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load manufacturers', ['error' => $e->getMessage()]);
            return collect();
        }
    }
}
