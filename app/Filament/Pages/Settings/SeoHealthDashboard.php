<?php

namespace App\Filament\Pages\Settings;

use App\Models\CoreWebVitalsSnapshot;
use App\Models\FailedSearchLog;
use App\Models\IndexNowPushLog;
use App\Models\NotFoundLog;
use App\Models\NotFoundLogSnapshot;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Redirect;
use App\Models\SearchLog;
use App\Models\SeoMeta;
use App\Services\CoreWebVitalsService;
use App\Services\GoogleSearchConsoleService;
use App\Services\RedirectLoopDetector;
use App\Services\SeoService;
use App\Support\LocaleRegistry;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Advanced SEO analytics/health surface, kept separate from
 * SeoControlCenter's settings-form lifecycle — Filament's own idiomatic
 * building block for live/polling stats is a plain widget-composed Page,
 * not a form page. Reached only via the Control Center's header action
 * (never in the sidebar nav, and not part of SettingsRegistry — it holds
 * no settings of its own, so SettingsRegistryTest's one-entry-per-page
 * rule does not apply to it).
 *
 * Originally 7 separate StatsOverviewWidget classes (one @livewire include
 * each) — consolidated into one page with its own Blade view (the same
 * "own view, own data methods" pattern BackupDashboard/HealthCheckStats
 * already use elsewhere in this admin, via the op-tile-grid/op-card/
 * op-status-pill/op-health-row CSS vocabulary) so this reads as one
 * cohesive dashboard instead of 7 visually disjoint generic stat blocks.
 */
class SeoHealthDashboard extends Page
{
    protected string $view = 'filament.pages.settings.seo-health-dashboard';

    protected static ?string $title = 'SEO Health Dashboard';

    protected static ?string $slug = 'seo-settings/health';

    protected static bool $shouldRegisterNavigation = false;

    /** Mirrors SettingsPage::canAccess() — this page sits alongside SeoControlCenter but doesn't extend it. */
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Search analytics, content coverage, and technical-SEO health — all in one place.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSeoSettings')
                ->label('Back to SEO Control Center')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(SeoControlCenter::getUrl()),
        ];
    }

    /**
     * @return array{total:int, zeroResult:int, zeroResultRate:int, ratioLabel:string}
     */
    public function searchAnalytics(): array
    {
        $since = Carbon::now()->subDays(30);

        $total = SearchLog::where('created_at', '>=', $since)->count();
        $zeroResult = FailedSearchLog::where('created_at', '>=', $since)->count();

        $byMatchType = SearchLog::where('created_at', '>=', $since)
            ->whereNotNull('match_type')
            ->selectRaw('match_type, COUNT(*) as c')
            ->groupBy('match_type')
            ->pluck('c', 'match_type');

        $matched = (int) $byMatchType->sum();
        $ratioLabel = $matched > 0
            ? sprintf(
                'Exact %d%% · Cross-ref %d%% · Partial %d%%',
                round(100 * ($byMatchType->get('exact', 0)) / $matched),
                round(100 * ($byMatchType->get('cross_reference', 0)) / $matched),
                round(100 * ($byMatchType->get('partial', 0)) / $matched),
            )
            : 'No matched searches yet';

        $zeroResultRate = ($total + $zeroResult) > 0
            ? (int) round(100 * $zeroResult / ($total + $zeroResult))
            : 0;

        return compact('total', 'zeroResult', 'zeroResultRate', 'ratioLabel');
    }

    /**
     * LocaleRegistry's own `name` field is the LANGUAGE name ("German"),
     * used for the site's language switcher — this dashboard instead wants
     * the COUNTRY name ("Germany") next to the flag, so it's mapped here
     * rather than repurposing LocaleRegistry's field for a second meaning.
     * Falls back to the language name for any locale not in this map.
     *
     * @var array<string, string>
     */
    private const COUNTRY_NAMES = [
        'de' => 'Germany',
        'lt' => 'Lithuania',
        'fr' => 'France',
        'es' => 'Spain',
        'en' => 'United Kingdom',
    ];

    /**
     * @return array{translations: array<int, array{code:string, name:string, flag:string, percent:int}>, manualPercent:int, manualCount:int, total:int}
     */
    public function contentHealth(): array
    {
        return Cache::remember('admin:seo-health:content', 300, function (): array {
            $total = Product::query()->active()->count();

            if ($total === 0) {
                return ['translations' => [], 'manualPercent' => 0, 'manualCount' => 0, 'total' => 0];
            }

            $default = LocaleRegistry::defaultCode();
            $translations = [];

            foreach (LocaleRegistry::languages() as $language) {
                if ($language['code'] === $default) {
                    continue;
                }

                $count = Product::query()->active()->where(fn ($q) => $q->whereRaw($this->jsonNotEmpty('name', $language['code'])))->count();

                $translations[] = [
                    'code' => $language['code'],
                    'name' => self::COUNTRY_NAMES[$language['code']] ?? $language['name'],
                    'flag' => $language['flag_emoji'],
                    'percent' => (int) round(100 * $count / $total),
                ];
            }

            $manualCount = Product::query()->active()->where(fn ($q) => $q->whereRaw($this->jsonNotEmpty('description', $default)))->count();

            return [
                'translations' => $translations,
                'manualPercent' => (int) round(100 * $manualCount / $total),
                'manualCount' => $manualCount,
                'total' => $total,
            ];
        });
    }

    private function jsonNotEmpty(string $column, string $locale): string
    {
        $path = DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract({$column}, '$.\"{$locale}\"')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$locale}\"'))";

        return "{$path} IS NOT NULL AND TRIM({$path}) != ''";
    }

    /**
     * "Manual Descriptions" (contentHealth() above) tracks the product's own
     * on-page `description` field — this tracks a different, SEO-specific
     * pair: the `seo_meta` overrides that control the search-result snippet
     * itself (meta title/description), which a product can lack even when
     * its on-page description is fully written. Duplicate titles are a
     * classic on-page SEO defect (two products competing with identical
     * search-result titles) and need no external tool to detect — it's a
     * GROUP BY over our own table.
     *
     * Also covers three more free, our-own-data-only checks: image alt text
     * (accessibility + image search, same jsonNotEmpty() pattern as
     * Translation Coverage), structured-data itemCondition completeness
     * (SeoService::conditionSchemaMap() — the only conditionally-omitted
     * field in productJsonLd(), so it's the only one where "complete" is a
     * meaningful signal), thin/orphan catalog entries (active, but with
     * neither cross-references nor car-model fitment — almost no content
     * for a crawler to index), and a canonical URL override that points
     * off this domain entirely (near-always a misconfiguration, since
     * `canonical_url` has no validation anywhere it's edited).
     *
     * @return array{total:int, customTitlePercent:int, customTitleCount:int, customDescriptionPercent:int, customDescriptionCount:int, duplicateTitleGroups:int, duplicateTitleProducts:int, altTextPercent:int, altTextCount:int, altTextTotal:int, conditionMappedPercent:int, conditionMappedCount:int, thinProductCount:int, offDomainCanonicalCount:int}
     */
    public function onPageAudit(): array
    {
        return Cache::remember('admin:seo-health:onpage', 300, function (): array {
            $total = Product::query()->active()->count();

            if ($total === 0) {
                return [
                    'total' => 0, 'customTitlePercent' => 0, 'customTitleCount' => 0,
                    'customDescriptionPercent' => 0, 'customDescriptionCount' => 0,
                    'duplicateTitleGroups' => 0, 'duplicateTitleProducts' => 0,
                    'altTextPercent' => 0, 'altTextCount' => 0, 'altTextTotal' => 0,
                    'conditionMappedPercent' => 0, 'conditionMappedCount' => 0,
                    'thinProductCount' => 0, 'offDomainCanonicalCount' => 0,
                ];
            }

            // A subquery (not ->pluck()->all()) — at catalog scale, pulling
            // every active product ID into PHP just to hand it back to MySQL
            // as a giant IN() list is real, avoidable overhead.
            $activeProductIds = fn () => Product::query()->active()->select('id');
            $default = LocaleRegistry::defaultCode();

            $customTitleCount = SeoMeta::query()
                ->where('metable_type', Product::class)
                ->whereIn('metable_id', $activeProductIds())
                ->whereNotNull('meta_title')
                ->where('meta_title', '!=', '')
                ->count();

            $customDescriptionCount = SeoMeta::query()
                ->where('metable_type', Product::class)
                ->whereIn('metable_id', $activeProductIds())
                ->whereNotNull('meta_description')
                ->where('meta_description', '!=', '')
                ->count();

            $duplicateGroupSizes = SeoMeta::query()
                ->where('metable_type', Product::class)
                ->whereIn('metable_id', $activeProductIds())
                ->whereNotNull('meta_title')
                ->where('meta_title', '!=', '')
                ->select('meta_title', DB::raw('COUNT(*) as cnt'))
                ->groupBy('meta_title')
                ->having('cnt', '>', 1)
                ->pluck('cnt');

            $altTextTotal = ProductImage::query()->whereIn('product_id', $activeProductIds())->count();
            $altTextCount = $altTextTotal > 0
                ? ProductImage::query()->whereIn('product_id', $activeProductIds())
                    ->where(fn ($q) => $q->whereRaw($this->jsonNotEmpty('alt_text', $default)))
                    ->count()
                : 0;

            $conditionMappedCount = Product::query()->active()
                ->whereHas('condition', fn ($q) => $q->whereIn('slug', array_keys(SeoService::conditionSchemaMap())))
                ->count();

            $thinProductCount = Product::query()->active()
                ->whereDoesntHave('crossReferences')
                ->whereDoesntHave('carModels')
                ->count();

            $canonicalHost = trim((string) settings('seo.canonical_host', ''));
            $offDomainCanonicalCount = $canonicalHost === '' ? 0 : SeoMeta::query()
                ->where('metable_type', Product::class)
                ->whereIn('metable_id', $activeProductIds())
                ->whereNotNull('canonical_url')
                ->where('canonical_url', '!=', '')
                ->where('canonical_url', 'not like', "%{$canonicalHost}%")
                ->count();

            return [
                'total' => $total,
                'customTitlePercent' => (int) round(100 * $customTitleCount / $total),
                'customTitleCount' => $customTitleCount,
                'customDescriptionPercent' => (int) round(100 * $customDescriptionCount / $total),
                'customDescriptionCount' => $customDescriptionCount,
                'duplicateTitleGroups' => $duplicateGroupSizes->count(),
                'duplicateTitleProducts' => (int) $duplicateGroupSizes->sum(),
                'altTextPercent' => $altTextTotal > 0 ? (int) round(100 * $altTextCount / $altTextTotal) : 0,
                'altTextCount' => $altTextCount,
                'altTextTotal' => $altTextTotal,
                'conditionMappedPercent' => (int) round(100 * $conditionMappedCount / $total),
                'conditionMappedCount' => $conditionMappedCount,
                'thinProductCount' => $thinProductCount,
                'offDomainCanonicalCount' => $offDomainCanonicalCount,
            ];
        });
    }

    /**
     * @return array{detailPagesEnabled:bool, indexNowEnabled:bool, ownImagePercent:int, withOwnImage:int, total:int}
     */
    public function featureAdoption(): array
    {
        $total = Product::query()->active()->count();
        $withOwnImage = $total > 0
            ? Product::query()->active()->whereHas('images', fn ($q) => $q->where('is_featured', true))->count()
            : 0;

        return [
            'detailPagesEnabled' => filter_var(settings('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'indexNowEnabled' => filter_var(settings('seo.indexnow_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'ownImagePercent' => $total > 0 ? (int) round(100 * $withOwnImage / $total) : 0,
            'withOwnImage' => $withOwnImage,
            'total' => $total,
        ];
    }

    /**
     * @return array{enabled:bool, lastStatus:?string, lastLabel:string, recentFailures:int}
     */
    public function indexNowActivity(): array
    {
        $last = IndexNowPushLog::query()->latest('created_at')->first();
        $recentFailures = IndexNowPushLog::query()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'enabled' => filter_var(settings('seo.indexnow_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'lastStatus' => $last?->status,
            'lastLabel' => $last
                ? ucfirst($last->status).' — '.$last->url_count.' URL(s), '.$last->created_at->diffForHumans()
                : 'No pushes recorded yet',
            'recentFailures' => $recentFailures,
        ];
    }

    /**
     * @return array{activeRedirects:int, loopCount:int, unresolved404s:int, brokenTargets:int}
     */
    public function redirectHealth(): array
    {
        return [
            'activeRedirects' => Redirect::query()->active()->count(),
            'loopCount' => count(app(RedirectLoopDetector::class)->findAllLoops()),
            'unresolved404s' => NotFoundLog::query()->where('resolved', false)->count(),
            'brokenTargets' => $this->brokenRedirectTargets(),
        ];
    }

    /**
     * Flags redirects whose target is itself a dead end — scoped to the one
     * internal URL shape this catalog actually redirects to at volume (the
     * OEM search-hub route, e.g. from OEM-normalization redirects): if the
     * target's OEM segment matches no active product, following the
     * redirect just lands on another zero-result page. A full link-checker
     * would need to actually fetch every target (new infra, outbound HTTP
     * calls); this catches the common, high-value case from data already
     * on hand.
     */
    private function brokenRedirectTargets(): int
    {
        $pattern = '#^/(?:'.LocaleRegistry::routePattern().')/parts/([A-Za-z0-9\-\.\s]+)$#';
        $broken = 0;

        Redirect::query()->active()->select('to_url')->chunk(200, function ($redirects) use ($pattern, &$broken) {
            foreach ($redirects as $redirect) {
                $path = parse_url($redirect->to_url, PHP_URL_PATH) ?: $redirect->to_url;

                if (! preg_match($pattern, $path, $m)) {
                    continue;
                }

                $exists = Product::query()->active()
                    ->where('normalized_oem', strtoupper(trim($m[1])))
                    ->exists();

                if (! $exists) {
                    $broken++;
                }
            }
        });

        return $broken;
    }

    /**
     * History for the 404-count trend bars — populated by the weekly
     * notfound:snapshot command (routes/console.php). A single data point
     * isn't a trend, so callers should treat fewer than 2 rows as "no
     * history yet."
     *
     * @return array<int, int>
     */
    public function notFoundTrend(): array
    {
        return NotFoundLogSnapshot::query()
            ->latest('recorded_at')
            ->limit(12)
            ->pluck('unresolved_count')
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * @return array{configured:bool, error?:string, indexed?:int, submitted?:int, errors?:int, warnings?:int}
     */
    public function googleSearchConsole(): array
    {
        $service = app(GoogleSearchConsoleService::class);

        if (! $service->isConfigured()) {
            return ['configured' => false];
        }

        $summary = Cache::remember('admin:seo-health:gsc', 300, fn () => $service->getSitemapSummary());

        if (isset($summary['error'])) {
            return ['configured' => true, 'error' => $summary['error']];
        }

        return array_merge(['configured' => true], $summary);
    }

    /**
     * @return array{configured:bool, error?:string, totalClicks?:int, totalImpressions?:int, avgCtr?:float, avgPosition?:float, topQueries?:array, topPages?:array}
     */
    public function googleSearchAnalytics(): array
    {
        $service = app(GoogleSearchConsoleService::class);

        if (! $service->isConfigured()) {
            return ['configured' => false];
        }

        $analytics = Cache::remember('admin:seo-health:gsc-analytics', 300, fn () => $service->getSearchAnalytics());

        if (isset($analytics['error'])) {
            return ['configured' => true, 'error' => $analytics['error']];
        }

        return array_merge(['configured' => true], $analytics);
    }

    /**
     * One homepage URL per active locale — a small, fixed set chosen
     * specifically to respect the URL Inspection API's low daily quota
     * (never scaled to the catalog). Cached for an hour, longer than the
     * other GSC data above, for the same quota reason.
     *
     * @return array{configured:bool, error?:string, results?:array<int, array{code:string, name:string, flag:string, url:string, verdict:string, coverageState:?string}>}
     */
    public function googleUrlInspection(): array
    {
        $service = app(GoogleSearchConsoleService::class);

        if (! $service->isConfigured()) {
            return ['configured' => false];
        }

        return Cache::remember('admin:seo-health:gsc-url-inspection', 3600, function () use ($service): array {
            $results = [];

            foreach (LocaleRegistry::languages() as $language) {
                $url = rtrim(url('/'), '/').'/'.$language['code'];
                $inspection = $service->inspectUrl($url);

                if (isset($inspection['error'])) {
                    return ['configured' => true, 'error' => $inspection['error']];
                }

                $results[] = [
                    'code' => $language['code'],
                    'name' => self::COUNTRY_NAMES[$language['code']] ?? $language['name'],
                    'flag' => $language['flag_emoji'],
                    'url' => $url,
                    'verdict' => $inspection['verdict'],
                    'coverageState' => $inspection['coverageState'],
                ];
            }

            return ['configured' => true, 'results' => $results];
        });
    }

    /**
     * @return array{configured:bool, insufficientData?:bool, error?:string, lcp?:array, cls?:array, inp?:array}
     */
    public function coreWebVitals(): array
    {
        $service = app(CoreWebVitalsService::class);

        if (! $service->isConfigured()) {
            return ['configured' => false];
        }

        $metrics = Cache::remember('admin:seo-health:crux', 300, fn () => $service->getMetrics());

        if (isset($metrics['insufficientData'])) {
            return ['configured' => true, 'insufficientData' => true];
        }

        if (isset($metrics['error'])) {
            return ['configured' => true, 'error' => $metrics['error']];
        }

        return [
            'configured' => true,
            'lcp' => ['value' => $metrics['lcp_ms'], 'unit' => 'ms', 'rating' => $service->lcpRating($metrics['lcp_ms'])],
            'cls' => ['value' => $metrics['cls'], 'unit' => '', 'rating' => $service->clsRating($metrics['cls'])],
            'inp' => ['value' => $metrics['inp_ms'], 'unit' => 'ms', 'rating' => $service->inpRating($metrics['inp_ms'])],
        ];
    }

    /**
     * Rating history for the trend bars — populated by the weekly
     * cwv:snapshot command (routes/console.php), not this page itself, so
     * this is purely a read of whatever has accumulated so far. A single
     * data point isn't a trend, so callers should treat fewer than 2 rows
     * as "no history yet" rather than rendering a one-bar chart.
     *
     * @return array{lcp: array<int, ?string>, cls: array<int, ?string>, inp: array<int, ?string>}
     */
    public function coreWebVitalsHistory(): array
    {
        $snapshots = CoreWebVitalsSnapshot::query()
            ->latest('recorded_at')
            ->limit(12)
            ->get(['lcp_rating', 'cls_rating', 'inp_rating'])
            ->reverse()
            ->values();

        return [
            'lcp' => $snapshots->pluck('lcp_rating')->all(),
            'cls' => $snapshots->pluck('cls_rating')->all(),
            'inp' => $snapshots->pluck('inp_rating')->all(),
        ];
    }

    /** Maps a CWV rating (good/needs-improvement/poor) to the op-status-pill tone suffix. */
    public function ratingTone(?string $rating): string
    {
        return match ($rating) {
            'good' => 'ok',
            'needs-improvement' => 'warn',
            'poor' => 'down',
            default => 'muted',
        };
    }
}
