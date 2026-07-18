<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Manufacturer;
use App\Models\Page;
use Illuminate\Http\Request;

/**
 * Human-readable HTML sitemap (a.k.a. "Document Index").
 *
 * Distinct from the machine-readable sitemap.xml served for search engines.
 * This one is navigable by humans — grouped by section, alphabetised, with
 * counters. Referenced from the footer.
 *
 * Route: /{lang}/sitemap  ·  name: frontend.sitemap
 */
class SitemapController extends Controller
{
    public function index(Request $request, string $lang)
    {
        // Manufacturers grouped A–Z using the current locale's name.
        $rawManufacturers = Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        $manufacturers = $rawManufacturers
            ->map(function (Manufacturer $m) use ($lang) {
                $label = trans_field($m->name, $lang) ?: $m->slug;
                return [
                    'label' => $label,
                    'slug'  => $m->slug,
                    'bucket' => strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/u', '', $label) ?: '#', 0, 1)),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $manufacturersByLetter = $manufacturers->groupBy('bucket')->sortKeys();

        // Blog posts — published, most recent first.
        $blogPostsQuery = BlogPost::query()
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $blogPostCount = (clone $blogPostsQuery)->count();

        $blogPosts = $blogPostsQuery
            ->orderByDesc('published_at')
            ->limit(20)
            ->get(['id', 'title', 'slug', 'published_at']);

        // CMS pages — published, and not the homepage-replacement one.
        $cmsPages = Page::query()
            ->where('status', ContentStatus::Published->value)
            ->where('is_homepage', false)
            ->orderBy('slug')
            ->get(['id', 'title', 'slug', 'updated_at']);

        // Split legal vs. general CMS pages for cleaner presentation.
        $legalSlugs = ['privacy-policy', 'privacy', 'terms-of-service', 'terms', 'cookie-policy', 'cookies', 'gdpr', 'imprint', 'impressum'];
        $legalPages   = $cmsPages->whereIn('slug', $legalSlugs)->values();
        $generalPages = $cmsPages->whereNotIn('slug', $legalSlugs)->values();

        return view('frontend.sitemap', [
            'lang'                  => $lang,
            'manufacturersByLetter' => $manufacturersByLetter,
            'manufacturerCount'     => $manufacturers->count(),
            'blogPosts'             => $blogPosts,
            'blogPostCount'         => $blogPostCount,
            'legalPages'            => $legalPages,
            'generalPages'          => $generalPages,
            'generatedAt'           => now(),
        ]);
    }
}
