<?php

namespace App\Services;

use App\Models\Condition;
use App\Models\FailedSearchLog;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductCrossReference;
use App\Models\SearchLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * OEM Search Engine — handles OEM number search with normalization.
 *
 * Search order:
 *   1. Exact match on normalized_oem (BTREE)
 *   2. Cross-reference match on normalized_cross_oem
 *   3. Partial match (LIKE %query%) if no exact
 *
 * Logs searches to search_logs/failed_search_logs unless $log is false (used
 * by internal/admin callers, e.g. the admin topbar's OEM lookup, so internal
 * traffic doesn't pollute customer-search analytics).
 */
class SearchService
{
    public function __construct(
        private OemNormalizerService $normalizer,
        private SettingsService $settings
    ) {}

    /**
     * Main search entry point.
     *
     * @param  string  $query  Raw OEM query from user
     * @param  int|null  $manufacturerId  Optional manufacturer filter
     * @param  int|null  $carModelId  Optional car model filter
     * @param  array  $options  Additional options (limit, paginate, per_page, sort, condition, in_stock_only)
     * @param  bool  $log  Whether to log the search and cache the result (false for internal/admin lookups)
     * @return array{products: Collection|LengthAwarePaginator, total: int, search_type: string, normalized_query: string, search_log_id: int|null, condition_counts: array}
     */
    public function search(
        string $query,
        ?int $manufacturerId = null,
        ?int $carModelId = null,
        array $options = [],
        bool $log = true
    ): array {
        $query = Str::limit(trim($query), 200);
        $normalized = $this->normalizer->normalize($query);
        $limit = $options['limit'] ?? (int) settings('search.results_limit', 50);
        $lang = $options['lang'] ?? 'en';
        $paginate = $options['paginate'] ?? false;
        $perPage = $options['per_page'] ?? (int) settings('search.per_page', 20);
        $sort = $options['sort'] ?? 'default';
        $condition = $options['condition'] ?? null;
        $inStockOnly = (bool) ($options['in_stock_only'] ?? false);

        $hasActiveFilters = $condition || $inStockOnly;

        $resolver = function () use ($query, $normalized, $lang, $manufacturerId, $carModelId, $limit, $paginate, $perPage, $sort, $condition, $inStockOnly, $hasActiveFilters, $log) {
            // Step 1: Exact match
            $exactResult = $this->exactMatch($normalized, $manufacturerId, $carModelId, $limit, $paginate, $perPage, $sort, $condition, $inStockOnly);
            if ($exactResult['total'] > 0) {
                $logId = $log ? $this->logSearch($query, $normalized, $lang, $exactResult['total'], $manufacturerId, $carModelId) : null;

                return $this->buildResult('exact', $exactResult, $normalized, $logId, $manufacturerId, $carModelId, $inStockOnly, $condition);
            }
            // Filters removed all exact results — surface filtered-empty instead of falling through
            if ($hasActiveFilters && $this->unfilteredCount('exact', $normalized, $manufacturerId, $carModelId) > 0) {
                return $this->filteredEmptyResult($normalized, 'exact', $manufacturerId, $carModelId, $inStockOnly, $condition);
            }

            // Step 2: Cross-reference match (if enabled)
            $crossRefEnabled = filter_var($this->settings->get('search.cross_ref_enabled', true), FILTER_VALIDATE_BOOLEAN);
            if ($crossRefEnabled) {
                $crossResult = $this->crossReferenceMatch($normalized, $manufacturerId, $carModelId, $limit, $paginate, $perPage, $sort, $condition, $inStockOnly);
                if ($crossResult['total'] > 0) {
                    $logId = $log ? $this->logSearch($query, $normalized, $lang, $crossResult['total'], $manufacturerId, $carModelId) : null;

                    return $this->buildResult('cross_reference', $crossResult, $normalized, $logId, $manufacturerId, $carModelId, $inStockOnly, $condition);
                }
                if ($hasActiveFilters && $this->unfilteredCount('cross_reference', $normalized, $manufacturerId, $carModelId) > 0) {
                    return $this->filteredEmptyResult($normalized, 'cross_reference', $manufacturerId, $carModelId, $inStockOnly, $condition);
                }
            }

            // Step 3: Partial match (if enabled and query long enough to avoid full-table scans)
            $minPartialLen = (int) $this->settings->get('search.partial_match_min_length', 4);
            $partialEnabled = $this->settings->get('search.allow_partial_match');
            if ($partialEnabled === null) {
                $partialEnabled = $this->settings->get('search.partial_match_enabled', true);
            }
            $partialEnabled = filter_var($partialEnabled, FILTER_VALIDATE_BOOLEAN);
            if ($partialEnabled && strlen($normalized) >= $minPartialLen) {
                $partialResult = $this->partialMatch($normalized, $manufacturerId, $carModelId, $limit, $paginate, $perPage, $sort, $condition, $inStockOnly);
                if ($partialResult['total'] > 0) {
                    $logId = $log ? $this->logSearch($query, $normalized, $lang, $partialResult['total'], $manufacturerId, $carModelId) : null;

                    return $this->buildResult('partial', $partialResult, $normalized, $logId, $manufacturerId, $carModelId, $inStockOnly, $condition);
                }
                if ($hasActiveFilters && $this->unfilteredCount('partial', $normalized, $manufacturerId, $carModelId) > 0) {
                    return $this->filteredEmptyResult($normalized, 'partial', $manufacturerId, $carModelId, $inStockOnly, $condition);
                }
            }

            // Zero results
            $logId = $log ? $this->logFailedSearch($query, $normalized, $lang, $manufacturerId, $carModelId) : null;

            return [
                'products' => collect(),
                'total' => 0,
                'search_type' => 'none',
                'normalized_query' => $normalized,
                'search_log_id' => $logId,
                'condition_counts' => [],
                'manufacturer_counts' => [],
                'price_stats' => ['min' => null, 'max' => null, 'avg' => null],
                'filtered_empty' => false,
                'unfiltered_total' => 0,
                // The zero-results page's status ledger shows a "Cross-refs · 0 hits"
                // row as if it were always a verified fact — it's only true when the
                // cross-reference step above actually ran (search.cross_ref_enabled).
                // Surfacing this lets the view avoid asserting "0 hits" when the check
                // was skipped entirely.
                'cross_ref_checked' => $crossRefEnabled,
            ];
        };

        if (! $log) {
            // Internal/admin lookups skip the shared result cache too — otherwise an
            // admin's query could populate the cache and cause a later real customer
            // search for the same term to silently skip its own logging.
            return $resolver();
        }

        $cacheKey = 'search:' . md5(implode('|', [
            $normalized, $manufacturerId ?? '', $carModelId ?? '',
            $limit, $paginate ? '1' : '0', $perPage, $sort,
            $condition ?? '', $inStockOnly ? '1' : '0',
        ]));

        return Cache::remember($cacheKey, 300, $resolver);
    }

    /**
     * Apply sort ordering to a query builder.
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default => $query->orderByDesc('is_in_stock')->orderBy('price', 'asc'),
        };
    }

    /**
     * Build a base query for a given match type — shared by aggregation helpers.
     */
    private function buildMatchQuery(string $matchType, string $normalized): Builder
    {
        return match ($matchType) {
            'exact' => Product::query()->where('products.normalized_oem', $normalized),
            'cross_reference' => (function () use ($normalized) {
                $ids = ProductCrossReference::where('normalized_cross_oem', $normalized)->pluck('product_id');

                return Product::query()->whereIn('products.id', $ids);
            })(),
            default => Product::query()->where('products.normalized_oem', 'LIKE', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $normalized) . '%'),
        };
    }

    /**
     * Count how many results exist for a match type ignoring condition/inStock filters.
     * Used to detect "filtered empty" state (has results but filters removed them all).
     */
    public function unfilteredCount(string $matchType, string $normalized, ?int $manufacturerId, ?int $carModelId): int
    {
        $q = $this->buildMatchQuery($matchType, $normalized)->where('is_active', true);
        if ($manufacturerId) {
            $q->where('manufacturer_id', $manufacturerId);
        }
        if ($carModelId) {
            $q->whereHas('carModels', fn ($q2) => $q2->where('car_model_id', $carModelId));
        }

        return $q->count();
    }

    /**
     * Get condition counts for filter pills — ignores active condition filter.
     */
    private function getConditionCounts(string $matchType, string $normalized, ?int $manufacturerId, ?int $carModelId, bool $inStockOnly): array
    {
        $q = $this->buildMatchQuery($matchType, $normalized)->where('products.is_active', true);
        if ($manufacturerId) {
            $q->where('products.manufacturer_id', $manufacturerId);
        }
        if ($carModelId) {
            $q->whereHas('carModels', fn ($q2) => $q2->where('car_model_id', $carModelId));
        }
        if ($inStockOnly) {
            $q->where('products.is_in_stock', true);
        }

        return $q->selectRaw('conditions.slug, COUNT(*) as cnt')
            ->join('conditions', 'products.condition_id', '=', 'conditions.id')
            ->groupBy('conditions.id', 'conditions.slug')
            ->pluck('cnt', 'conditions.slug')
            ->toArray();
    }

    /**
     * Get per-manufacturer counts for filter pills — ignores active manufacturer filter.
     */
    public function getManufacturerCounts(string $matchType, string $normalized, ?int $carModelId, bool $inStockOnly, ?string $condition): array
    {
        $cacheKey = 'mfr_counts:' . md5(implode('|', [$matchType, $normalized, $carModelId ?? '', $inStockOnly ? '1' : '0', $condition ?? '']));

        return Cache::remember($cacheKey, 300, function () use ($matchType, $normalized, $carModelId, $inStockOnly, $condition) {
            $q = $this->buildMatchQuery($matchType, $normalized)->where('is_active', true);
            if ($carModelId) {
                $q->whereHas('carModels', fn ($q2) => $q2->where('car_model_id', $carModelId));
            }
            if ($inStockOnly) {
                $q->where('is_in_stock', true);
            }
            if ($condition) {
                $q->whereHas('condition', fn ($q2) => $q2->where('slug', $condition));
            }

            return $q->selectRaw('manufacturer_id, COUNT(*) as cnt')->groupBy('manufacturer_id')->pluck('cnt', 'manufacturer_id')->toArray();
        });
    }

    /**
     * Get min/max/avg price stats for the current result set.
     */
    public function getPriceStats(string $matchType, string $normalized, ?int $manufacturerId, ?int $carModelId, bool $inStockOnly, ?string $condition): array
    {
        $q = $this->buildMatchQuery($matchType, $normalized)->where('is_active', true);
        if ($manufacturerId) {
            $q->where('manufacturer_id', $manufacturerId);
        }
        if ($carModelId) {
            $q->whereHas('carModels', fn ($q2) => $q2->where('car_model_id', $carModelId));
        }
        if ($inStockOnly) {
            $q->where('is_in_stock', true);
        }
        if ($condition) {
            $q->whereHas('condition', fn ($q2) => $q2->where('slug', $condition));
        }
        $stats = $q->selectRaw('MIN(price) as min_price, MAX(price) as max_price, AVG(price) as avg_price')->first();
        $avgNumeric = null;
        if ($stats->avg_price !== null) {
            $avgNumeric = bcadd((string) $stats->avg_price, '0', 6);
        }

        return [
            'min' => $stats->min_price !== null ? bcadd((string) $stats->min_price, '0', 2) : null,
            'max' => $stats->max_price !== null ? bcadd((string) $stats->max_price, '0', 2) : null,
            'avg' => $stats->avg_price !== null ? bcadd((string) $stats->avg_price, '0', 2) : null,
            'avg_numeric' => $avgNumeric,
        ];
    }

    /**
     * Build a standard successful search result array.
     */
    private function buildResult(string $matchType, array $matchResult, string $normalized, ?int $logId, ?int $manufacturerId, ?int $carModelId, bool $inStockOnly, ?string $condition): array
    {
        return [
            'products' => $matchResult['products'],
            'total' => $matchResult['total'],
            'search_type' => $matchType,
            'normalized_query' => $normalized,
            'search_log_id' => $logId,
            'condition_counts' => $this->getConditionCounts($matchType, $normalized, $manufacturerId, $carModelId, $inStockOnly),
            'manufacturer_counts' => $this->getManufacturerCounts($matchType, $normalized, $carModelId, $inStockOnly, $condition),
            'price_stats' => $this->getPriceStats($matchType, $normalized, $manufacturerId, $carModelId, $inStockOnly, $condition),
            'filtered_empty' => false,
            'unfiltered_total' => 0,
        ];
    }

    /**
     * Build a filtered-empty result (has results without filters, but active filters removed them all).
     */
    private function filteredEmptyResult(string $normalized, string $matchType, ?int $manufacturerId, ?int $carModelId, bool $inStockOnly, ?string $condition): array
    {
        $unfilteredTotal = $this->unfilteredCount($matchType, $normalized, $manufacturerId, $carModelId);

        return [
            'products' => collect(),
            'total' => 0,
            'search_type' => $matchType,
            'normalized_query' => $normalized,
            'search_log_id' => null,
            'condition_counts' => $this->getConditionCounts($matchType, $normalized, $manufacturerId, $carModelId, false),
            'manufacturer_counts' => $this->getManufacturerCounts($matchType, $normalized, $carModelId, false, null),
            'price_stats' => $this->getPriceStats($matchType, $normalized, $manufacturerId, $carModelId, false, null),
            'filtered_empty' => true,
            'unfiltered_total' => $unfilteredTotal,
        ];
    }

    /**
     * Exact match on normalized_oem column.
     */
    private function exactMatch(
        string $normalized,
        ?int $manufacturerId,
        ?int $carModelId,
        int $limit,
        bool $paginate = false,
        int $perPage = 20,
        string $sort = 'default',
        ?string $condition = null,
        bool $inStockOnly = false
    ) {
        $query = Product::query()
            ->where('normalized_oem', $normalized)
            ->where('is_active', true)
            ->with(['manufacturer.logo', 'crossReferences', 'condition']);

        if ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }
        if ($carModelId) {
            $query->whereHas('carModels', fn ($q) => $q->where('car_model_id', $carModelId));
        }
        if ($condition) {
            $query->whereHas('condition', fn ($q) => $q->where('slug', $condition));
        }
        if ($inStockOnly) {
            $query->where('is_in_stock', true);
        }

        $this->applySort($query, $sort);

        if ($paginate) {
            $paginator = $query->paginate($perPage);

            return ['products' => $paginator, 'total' => $paginator->total()];
        }

        $collection = $query->limit($limit)->get();

        return ['products' => $collection, 'total' => $collection->count()];
    }

    /**
     * Cross-reference match via product_cross_references table.
     */
    private function crossReferenceMatch(
        string $normalized,
        ?int $manufacturerId,
        ?int $carModelId,
        int $limit,
        bool $paginate = false,
        int $perPage = 20,
        string $sort = 'default',
        ?string $condition = null,
        bool $inStockOnly = false
    ) {
        $productIds = ProductCrossReference::query()
            ->where('normalized_cross_oem', $normalized)
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return ['products' => collect(), 'total' => 0];
        }

        $query = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->with(['manufacturer.logo', 'crossReferences', 'condition']);

        if ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }
        if ($carModelId) {
            $query->whereHas('carModels', fn ($q) => $q->where('car_model_id', $carModelId));
        }
        if ($condition) {
            $query->whereHas('condition', fn ($q) => $q->where('slug', $condition));
        }
        if ($inStockOnly) {
            $query->where('is_in_stock', true);
        }

        $this->applySort($query, $sort);

        if ($paginate) {
            $paginator = $query->paginate($perPage);

            return ['products' => $paginator, 'total' => $paginator->total()];
        }

        $collection = $query->limit($limit)->get();

        return ['products' => $collection, 'total' => $collection->count()];
    }

    /**
     * Partial match using LIKE on normalized_oem.
     */
    private function partialMatch(
        string $normalized,
        ?int $manufacturerId,
        ?int $carModelId,
        int $limit,
        bool $paginate = false,
        int $perPage = 20,
        string $sort = 'default',
        ?string $condition = null,
        bool $inStockOnly = false
    ) {
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $normalized);
        $query = Product::query()
            ->where('normalized_oem', 'LIKE', "%{$escaped}%")
            ->where('is_active', true)
            ->with(['manufacturer.logo', 'crossReferences', 'condition']);

        if ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }
        if ($carModelId) {
            $query->whereHas('carModels', fn ($q) => $q->where('car_model_id', $carModelId));
        }
        if ($condition) {
            $query->whereHas('condition', fn ($q) => $q->where('slug', $condition));
        }
        if ($inStockOnly) {
            $query->where('is_in_stock', true);
        }

        $this->applySort($query, $sort);

        if ($paginate) {
            $paginator = $query->paginate($perPage);

            return ['products' => $paginator, 'total' => $paginator->total()];
        }

        $collection = $query->limit($limit)->get();

        return ['products' => $collection, 'total' => $collection->count()];
    }

    /**
     * Autocomplete search for AJAX dropdown.
     *
     * @param  string  $lang  Language code (e.g., 'en', 'de')
     */
    public function autocomplete(string $query, string $lang, int $limit = 5): array
    {
        $normalized = $this->normalizer->normalize($query);
        $minChars = (int) $this->settings->get('search.min_chars', 3);
        if (strlen($normalized) < $minChars) {
            return [];
        }

        // Backs every OEM search-box keystroke sitewide — was fully-loaded
        // columns + an unloaded `condition` relation (N+1 per row) + uncached.
        $cacheKey = 'search:autocomplete:' . md5(implode('|', [$normalized, $lang, $limit]));

        return Cache::remember($cacheKey, 300, function () use ($normalized, $lang, $limit) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $normalized);
            $products = Product::query()
                ->select(['id', 'oem_number', 'normalized_oem', 'price', 'condition_id', 'is_in_stock', 'name', 'manufacturer_id'])
                ->where('normalized_oem', 'LIKE', "{$escaped}%")
                ->where('is_active', true)
                ->with(['manufacturer:id,name', 'condition:id,slug,name'])
                ->limit($limit)
                ->get();

            return $products->map(function (Product $product) use ($lang) {
                $name = is_array($product->name) ? trans_field($product->name, $lang) : null;

                return [
                    'id' => $product->id,
                    'oem' => $product->oem_number,
                    'normalized_oem' => $product->normalized_oem,
                    'manufacturer' => $product->manufacturer ? trans_field($product->manufacturer->name, $lang) : null,
                    'title' => filled($name) ? $name : null,
                    'price' => bcadd((string) $product->price, '0', 2),
                    'condition' => $product->condition?->slug ?? 'new',
                    'condition_label' => $product->condition?->name ?? 'New',
                    'in_stock' => (bool) $product->is_in_stock,
                    'url' => route('frontend.search.results', ['lang' => $lang, 'oem' => $product->normalized_oem]),
                ];
            })->toArray();
        });
    }

    /**
     * Log successful search.
     */
    private function logSearch(
        string $rawQuery,
        string $normalized,
        string $lang = 'en',
        int $resultCount = 0,
        ?int $manufacturerId = null,
        ?int $carModelId = null
    ): ?int {
        if (! filter_var($this->settings->get('search.log_searches', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        try {
            $log = SearchLog::create([
                'search_query' => $rawQuery,
                'normalized_query' => $normalized,
                'result_count' => $resultCount,
                'manufacturer_id' => $manufacturerId,
                'car_model_id' => $carModelId,
                'lang' => $lang,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_id' => auth()->id(),
            ]);

            return $log->id;
        } catch (\Exception $e) {
            // Silently fail logging — search should not break because of logs
            return null;
        }
    }

    /**
     * Log failed search (zero results).
     */
    private function logFailedSearch(
        string $rawQuery,
        string $normalized,
        string $lang = 'en',
        ?int $manufacturerId = null,
        ?int $carModelId = null
    ): ?int {
        if (! filter_var($this->settings->get('search.log_failed', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        try {
            $log = FailedSearchLog::create([
                'search_query' => $rawQuery,
                'normalized_query' => $normalized,
                'manufacturer_id' => $manufacturerId,
                'car_model_id' => $carModelId,
                'lang' => $lang,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_id' => auth()->id(),
                'inquiry_submitted' => false,
            ]);

            return $log->id;
        } catch (\Exception $e) {
            // Silently fail
            return null;
        }
    }
}
