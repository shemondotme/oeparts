<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService
    ) {}

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

        // Rate limit search requests (30 per minute per IP)
        $maxSearches = (int) settings('search.rate_limit_per_minute', 30);
        if (!RateLimiter::attempt("search:{$request->ip()}", $maxSearches, function () {
            return true;
        })) {
            throw new TooManyRequestsHttpException(60, 'Too many search requests. Please slow down.');
        }

        // Optional manufacturer/model filters from query string
        $manufacturerId = $request->query('manufacturer');
        $carModelId     = $request->query('model');

        // Sort + filter params
        $sort      = in_array($request->query('sort'), ['price_asc', 'price_desc', 'default'], true)
                     ? $request->query('sort')
                     : 'default';
        $validConditionSlugs = Condition::where('is_active', true)->pluck('slug')->toArray();
        $condition = in_array($request->query('condition'), $validConditionSlugs, true)
                     ? $request->query('condition')
                     : null;
        $inStockOnly = $request->boolean('in_stock');

        $result = $this->searchService->search(
            query: $oem,
            manufacturerId: $manufacturerId ? (int) $manufacturerId : null,
            carModelId: $carModelId ? (int) $carModelId : null,
            options: [
                'limit'        => settings('search.results_limit', 100),
                'lang'         => $lang,
                'paginate'     => true,
                'per_page'     => settings('search.per_page', 20),
                'sort'         => $sort,
                'condition'    => $condition,
                'in_stock_only' => $inStockOnly,
            ]
        );

        // Filtered-empty: has results but active filters removed them all → stay on results page
        if ($result['filtered_empty']) {
            return view('frontend.search.results', array_merge(
                $this->buildResultsViewData($result, $lang, $sort, $condition, $inStockOnly, $manufacturerId, $carModelId),
                ['filtered_empty' => true, 'unfiltered_total' => $result['unfiltered_total']]
            ));
        }

        // True zero results → zero-results page
        if ($result['total'] === 0) {
            return view('frontend.search.zero-results', [
                'normalized_query' => $result['normalized_query'],
                'search_type'      => $result['search_type'],
                'popularOems'      => $this->getPopularOems(),
            ]);
        }

        return view('frontend.search.results',
            $this->buildResultsViewData($result, $lang, $sort, $condition, $inStockOnly, $manufacturerId, $carModelId)
        );
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
        // COUNT query on every console load).
        $stats = Cache::remember(
            'search_console_stats',
            now()->addHours((int) settings('search.cache_ttl_hours', 6)),
            fn () => [
                'brands'   => Manufacturer::where('is_active', true)->count(),
                'products' => Product::where('is_active', true)->count(),
            ]
        );

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
    private function buildResultsViewData(array $result, string $lang, string $sort, ?string $condition, bool $inStockOnly, ?int $manufacturerId, ?int $carModelId): array
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
