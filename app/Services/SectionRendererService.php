<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Testimonial;
use App\Models\Faq;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\SearchLog;
use App\Enums\SectionLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * SectionRendererService — loads and renders homepage sections.
 *
 * Sections are cached via CacheService. Each section type maps to a Blade
 * component in resources/views/components/sections/{kebab-case-type}.blade.php
 * — the stored `type` column is snake_case (e.g. "blog_preview"), converted
 * to kebab-case at include time in home.blade.php.
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
     * Now filters by published status.
     */
    public function getSections(string $location): Collection
    {
        return $this->cache->rememberSection($location, function () use ($location) {
            return Section::where('location', $location)
                ->published()  // Only published sections
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

        if (in_array('hero', $types)) {
            $data['hero_stats']    = $this->loadHeroStats();
            $data['popular_oems']  = $this->loadPopularOems();
        }

        return $data;
    }

    // ── Private data loaders ──────────────────────────────────────────────────

    private function loadHeroStats(): array
    {
        try {
            return $this->cache->rememberHeroStats(function () {
                $partsCount  = Product::where('is_active', true)->count();
                $mfrCount    = Manufacturer::where('is_active', true)->count();
                $crossCount  = ProductCrossReference::count();

                return [
                    'parts_count'   => number_format($partsCount),
                    'manufacturers' => (string) $mfrCount,
                    'cross_refs'    => $this->formatLargeNumber($crossCount),
                ];
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load hero stats', ['error' => $e->getMessage()]);

            return [
                'parts_count'   => number_format((int) settings('stats_counter.parts_count', 1000000)),
                'manufacturers' => settings('ui.hero_spec_r2_value', '214'),
                'cross_refs'    => settings('ui.hero_spec_r3_value', '3.2M'),
            ];
        }
    }

    private function loadPopularOems(): array
    {
        try {
            return $this->cache->rememberPopularOems(function () {
                // Primary: most-searched OEM queries (last 30 days, searches that returned results)
                $normalized = SearchLog::query()
                    ->select('normalized_query', DB::raw('COUNT(*) as cnt'))
                    ->where('created_at', '>=', now()->subDays(30))
                    ->where('result_count', '>', 0)
                    ->groupBy('normalized_query')
                    ->orderByDesc('cnt')
                    ->limit(12)
                    ->pluck('normalized_query');

                if ($normalized->isNotEmpty()) {
                    $oemMap = Product::whereIn('normalized_oem', $normalized->toArray())
                        ->where('is_active', true)
                        ->pluck('oem_number', 'normalized_oem');

                    $oems = $normalized
                        ->map(fn ($n) => $oemMap->get($n))
                        ->filter()
                        ->take(6)
                        ->values()
                        ->toArray();

                    if (count($oems) >= 4) {
                        return $oems;
                    }
                }

                // Fallback: recently added active in-stock products
                return Product::where('is_active', true)
                    ->where('is_in_stock', true)
                    ->orderByDesc('created_at')
                    ->limit(6)
                    ->pluck('oem_number')
                    ->toArray();
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load popular OEMs', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function formatLargeNumber(int $n): string
    {
        if ($n >= 1_000_000) {
            return number_format($n / 1_000_000, 1) . 'M+';
        }
        if ($n >= 1_000) {
            return number_format($n / 1_000, 1) . 'K+';
        }
        return (string) $n;
    }

    private function loadTestimonials(): Collection
    {
        try {
            return $this->cache->rememberTestimonials(function () {
                return Testimonial::where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit((int) settings('sections.testimonials_limit', 6))
                    ->get();
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load testimonials', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function loadFaqs(): Collection
    {
        try {
            return $this->cache->rememberFaqs(function () {
                return Faq::where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit((int) settings('sections.faq_limit', 8))
                    ->get();
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load faqs', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function loadBlogPosts(): Collection
    {
        try {
            return $this->cache->rememberHomeBlogPosts(function () {
                return BlogPost::where('status', 'published')
                    ->with(['category', 'author'])
                    ->whereNotNull('published_at')
                    ->orderByDesc('published_at')
                    ->limit((int) settings('sections.blog_limit', 6))
                    ->get();
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load blog posts', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function loadManufacturers(): Collection
    {
        try {
            return app(CacheService::class)->rememberManufacturers(function () {
                return Manufacturer::with('logo')
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->limit((int) settings('sections.manufacturers_limit', 12))
                    ->get();
            });
        } catch (\Exception $e) {
            Log::warning('SectionRendererService: failed to load manufacturers', ['error' => $e->getMessage()]);
            return collect();
        }
    }
}
