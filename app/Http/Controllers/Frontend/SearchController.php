<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CacheService;
use App\Services\CrawlerVerificationService;
use App\Services\ProductSlugService;
use App\Services\SearchService;
use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService,
        private CacheService $cacheService,
        private CrawlerVerificationService $crawlerVerification,
        private ProductSlugService $productSlugService,
    ) {}

    /**
     * Rate limit search requests (default 30/min/IP) — shared by results()
     * and detail(), so both routes get the same crawler exemption from one
     * place. Verified Googlebot/Bingbot IPs bypass the limiter entirely;
     * everyone else keeps the existing behavior unchanged.
     */
    private function enforceSearchRateLimit(Request $request): void
    {
        if ($this->crawlerVerification->isVerifiedCrawler($request->ip() ?? '')) {
            return;
        }

        $maxSearches = (int) settings('search.rate_limit_per_minute', 30);
        if (!RateLimiter::attempt("search:{$request->ip()}", $maxSearches, function () {
            return true;
        })) {
            // No message here — bootstrap/app.php's TooManyRequestsHttpException
            // renderer falls back to the translated search.error_429_message
            // ONLY when getMessage() is empty; a hardcoded string here would
            // silently win over the per-locale translation for every visitor.
            throw new TooManyRequestsHttpException(60);
        }
    }

    /**
     * Show search results for an OEM number.
     *
     * Route: /{lang}/parts/{oem}
     * Constraint: oem = [A-Z0-9]+
     */
    public function results(Request $request, string $lang, string $oem)
    {
        // Validate OEM format (should already be normalized via middleware)
        if (!preg_match('/^[A-Z0-9]+$/', $oem)) {
            abort(404);
        }

        $this->enforceSearchRateLimit($request);

        $manufacturerId = $request->query('manufacturer');
        $carModelId     = $request->query('model');

        // Sort + filter params
        $sort      = in_array($request->query('sort'), ['price_asc', 'price_desc', 'default'], true)
                     ? $request->query('sort')
                     : 'default';
        $activeConditions = app(CacheService::class)->rememberActiveConditions(
            fn () => Condition::where('is_active', true)->orderBy('sort_order')->get()
        );
        $validConditionSlugs = $activeConditions->pluck('slug')->toArray();
        $condition = in_array($request->query('condition'), $validConditionSlugs, true)
                     ? $request->query('condition')
                     : null;
        $inStockOnly = $request->boolean('in_stock');

        // No pagination — the whole hub page shows up to results_limit
        // (default 100) on one page. Removes the entire class of "page
        // 2+ never gets a crawl signal" gap (robots.txt blocked ?page=,
        // and canonical always collapsed to page 1) rather than patching
        // it: a 100-result comparison page is normal e-commerce length,
        // not "thin infinite pagination" the way a general catalog browse
        // page would be.
        $result = $this->searchService->search(
            query: $oem,
            manufacturerId: $manufacturerId ? (int) $manufacturerId : null,
            carModelId: $carModelId ? (int) $carModelId : null,
            options: [
                'limit'        => settings('search.results_limit', 100),
                'lang'         => $lang,
                'paginate'     => false,
                'sort'         => $sort,
                'condition'    => $condition,
                'in_stock_only' => $inStockOnly,
            ]
        );

        // Filtered-empty: has results but active filters removed them all → stay on results page
        if ($result['filtered_empty']) {
            return view('frontend.search.results', array_merge(
                $this->buildResultsViewData($result, $lang, $sort, $condition, $inStockOnly, $manufacturerId, $carModelId, $activeConditions),
                ['filtered_empty' => true, 'unfiltered_total' => $result['unfiltered_total']]
            ));
        }

        // True zero results → zero-results page. Real HTTP 404 (previously
        // 200 — a "soft 404" that told Google this URL was a valid,
        // permanent page with no content, rather than "nothing here."
        // Content/copy is unchanged, only the status code.
        if ($result['total'] === 0) {
            return response()->view('frontend.search.zero-results', [
                'normalized_query'     => $result['normalized_query'],
                'search_type'          => $result['search_type'],
                'popularOems'          => $this->getPopularOems(),
                'failed_search_log_id' => $result['search_log_id'],
                'cross_ref_checked'    => $result['cross_ref_checked'] ?? true,
            ], 404);
        }

        // Single confirmed match (exact or cross-reference — NOT partial,
        // a substring hit isn't a confirmed equality match, and silently
        // redirecting a fuzzy hit away from its "partial match" context
        // would be a real trust/UX regression) auto-redirects straight to
        // the product's own detail page, skipping a 1-row hub page —
        // Hub+Detail mode only.
        if ($result['total'] === 1 && in_array($result['search_type'], ['exact', 'cross_reference'], true)
            && filter_var(settings('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $product = $result['products']->first();

            if ($product) {
                return redirect()->route('frontend.search.detail', [
                    'lang' => $lang,
                    'oem' => $product->normalized_oem,
                    'idSlug' => $this->productSlugService->buildIdSlug($product, $lang),
                ], 301);
            }
        }

        return view('frontend.search.results',
            $this->buildResultsViewData($result, $lang, $sort, $condition, $inStockOnly, $manufacturerId, $carModelId, $activeConditions)
        );
    }

    /**
     * Per-product detail page.
     *
     * Route: /{lang}/parts/{oem}/{idSlug}
     */
    public function detail(Request $request, string $lang, string $oem, string $idSlug)
    {
        $this->enforceSearchRateLimit($request);

        $id = (int) strtok($idSlug, '-');

        $product = Product::withTrashed()
            ->with(['manufacturer.logo', 'crossReferences', 'condition', 'carModels', 'images'])
            ->find($id);

        // Toggle check FIRST — this single check is what makes "toggle off
        // after going live," "stale bookmarked link," and "toggle off then
        // on then off again" all collapse into one code path, rather than
        // needing separate handling for each.
        $detailPagesEnabled = filter_var(settings('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN);
        if (! $detailPagesEnabled) {
            return $this->redirectToHub($lang, $product, $oem);
        }

        // Discontinued or soft-deleted — 301 to the hub rather than a dead
        // end, so old links/index entries don't 404.
        if (! $product || $product->trashed() || ! $product->is_active) {
            return $this->redirectToHub($lang, $product, $oem);
        }

        // Canonical drift — the URL's own OEM segment or slug half no
        // longer matches the product's current values (renamed product,
        // OEM corrected, etc.). Consistent with this codebase's existing
        // 301-correctness philosophy (NormalizeOemUrl, redirect-loop
        // validation) rather than silently rendering under a stale URL.
        $canonicalIdSlug = $this->productSlugService->buildIdSlug($product, $lang);
        if ($oem !== $product->normalized_oem || $idSlug !== $canonicalIdSlug) {
            return redirect()->route('frontend.search.detail', [
                'lang' => $lang,
                'oem' => $product->normalized_oem,
                'idSlug' => $canonicalIdSlug,
            ], 301);
        }

        return view('frontend.search.detail', [
            'product' => $product,
            'breadcrumbs' => $this->buildProductBreadcrumbs($product, $lang),
        ]);
    }

    /**
     * 301s to the hub page for whatever OEM we can resolve — the product's
     * own normalized_oem if it still exists, else the URL's own (already-
     * normalized, per NormalizeOemUrl) oem segment. Shared by every
     * detail() branch that needs to bail out to the hub.
     */
    private function redirectToHub(string $lang, ?Product $product, string $fallbackOem)
    {
        return redirect()->route('frontend.search.results', [
            'lang' => $lang,
            'oem' => $product?->normalized_oem ?? $fallbackOem,
        ], 301);
    }

    /**
     * Home > Manufacturer (if any) > OEM search results (hub) > this
     * product — the detail page's own breadcrumb chain. Deliberately
     * separate from buildResultsViewData()'s breadcrumb block, which
     * reflects the HUB page's active query-string filters (manufacturer/
     * car-model), a genuinely different concept from "where does this one
     * product sit in the site hierarchy."
     */
    private function buildProductBreadcrumbs(Product $product, string $lang): array
    {
        $breadcrumbs = [];

        if ($product->manufacturer) {
            $breadcrumbs[] = [
                'label' => trans_field($product->manufacturer->name, $lang),
                'url' => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $product->manufacturer->slug]),
            ];
        }

        $breadcrumbs[] = [
            'label' => $product->oem_number,
            'url' => route('frontend.search.results', ['lang' => $lang, 'oem' => $product->normalized_oem]),
        ];

        return $breadcrumbs;
    }

    /**
     * Search Console landing page — a dedicated empty-state search experience
     * that any "Browse parts" / "Parts search" CTA across the site can link to.
     *
     * Route: /{lang}/parts
     */
    public function console(Request $request, string $lang)
    {
        $popularOems = $this->getPopularOems();

        // Featured brands (top, active, verified OEM) for quick entry shortcuts.
        $featuredBrands = Manufacturer::where('is_active', true)
            ->orderByDesc('is_verified_oem')
            ->orderBy('sort_order')
            ->limit(8)
            ->get(['id', 'name', 'slug']);

        $minChars = (int) settings('search.min_chars', 3);

        // Catalogue stats for the status panel — cached (rarely change; avoid a
        // COUNT query on every console load), invalidated by ProductObserver/
        // ManufacturerObserver on every write.
        $stats = $this->cacheService->rememberSearchConsoleStats(fn () => [
            'brands'   => Manufacturer::where('is_active', true)->count(),
            'products' => Product::where('is_active', true)->count(),
        ]);

        return view('frontend.search.console', [
            'lang'           => $lang,
            'popularOems'    => $popularOems,
            'featuredBrands' => $featuredBrands,
            'minChars'       => $minChars,
            'brandCount'     => $stats['brands'],
            'productCount'   => $stats['products'],
        ]);
    }

    /**
     * Build the shared data array for the results view.
     */
    private function buildResultsViewData(array $result, string $lang, string $sort, ?string $condition, bool $inStockOnly, ?int $manufacturerId, ?int $carModelId, \Illuminate\Support\Collection $activeConditions): array
    {
        // Breadcrumbs + car model entity (single query for filter chip / Alpine)
        $breadcrumbs = [];
        $carModelEntity = null;
        if ($manufacturerId && $manufacturer = Manufacturer::find($manufacturerId)) {
            $breadcrumbs[] = [
                'label' => trans_field($manufacturer->name),
                'url'   => route('frontend.manufacturer.show', ['lang' => $lang, 'manufacturer' => $manufacturer->slug]),
            ];
        }
        if ($carModelId) {
            $carModelEntity = CarModel::with('manufacturer')->find($carModelId);
            if ($carModelEntity) {
                $breadcrumbs[] = [
                    'label' => $carModelEntity->name,
                    'url'   => route('frontend.car-model.show', [
                        'lang' => $lang,
                        'manufacturer' => $carModelEntity->manufacturer->slug,
                        'model' => $carModelEntity->slug,
                    ]),
                ];
            }
        }

        // Build manufacturer filter options from counts, load names
        $manufacturerFilterOptions = [];
        if (!empty($result['manufacturer_counts'])) {
            $mfrIds = array_keys($result['manufacturer_counts']);
            $manufacturers = Manufacturer::whereIn('id', $mfrIds)->get()->keyBy('id');
            foreach ($result['manufacturer_counts'] as $mfrId => $cnt) {
                if ($mfr = $manufacturers->get($mfrId)) {
                    $manufacturerFilterOptions[] = [
                        'id'    => $mfrId,
                        'name'  => trans_field($mfr->name),
                        'count' => $cnt,
                    ];
                }
            }
            usort($manufacturerFilterOptions, fn($a, $b) => $b['count'] - $a['count']);
        }

        return [
            'products'                   => $result['products'],
            'total'                      => $result['total'],
            'search_type'                => $result['search_type'],
            'normalized_query'           => $result['normalized_query'],
            'breadcrumbs'                => $breadcrumbs,
            'sort'                       => $sort,
            'condition_filter'           => $condition,
            'in_stock_only'              => $inStockOnly,
            'manufacturer_filter'        => $manufacturerId,
            'car_model_filter'           => $carModelEntity ? $carModelId : null,
            'car_model_filter_label'     => $carModelEntity?->name,
            'condition_counts'           => $result['condition_counts'],
            'conditions'                 => $activeConditions,
            'manufacturer_filter_options' => $manufacturerFilterOptions,
            'price_stats'                => $result['price_stats'],
            'vat_rate'                   => (int) settings('tax.default_vat_rate', 21),
            'filtered_empty'             => false,
            'unfiltered_total'           => 0,
        ];
    }

    /**
     * Fetch top 4 popular OEM numbers from the last 30 days (for zero-results suggestions).
     */
    private function getPopularOems(): \Illuminate\Support\Collection
    {
        try {
            $lang = app()->getLocale();
            $cacheKey = 'popular_oems_zero_results_norm_' . $lang;
            return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addHours((int) settings('search.cache_ttl_hours', 6)), function () {
                return \DB::table('search_logs')
                    ->select('normalized_query', \DB::raw('COUNT(*) as hits'))
                    ->where('created_at', '>=', now()->subDays((int) settings('search.popular_days_window', 30)))
                    ->where('result_count', '>', 0)
                    ->where('normalized_query', '!=', '')
                    ->groupBy('normalized_query')
                    ->orderByDesc('hits')
                    ->limit((int) settings('search.popular_limit', 4))
                    ->pluck('normalized_query');
            });
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Autocomplete endpoint for AJAX requests.
     *
     * Route: /{lang}/search/autocomplete
     */
    public function autocomplete(Request $request, string $lang)
    {
        $query = $request->query('q', '');
        $query = trim($query);

        $minChars = (int) settings('search.min_chars', 3);
        if (strlen($query) < $minChars) {
            return response()->json([]);
        }

        $limit = (int) settings('search.autocomplete_count', 5);
        $results = $this->searchService->autocomplete($query, $lang, $limit);
        return response()->json($results);
    }
}
