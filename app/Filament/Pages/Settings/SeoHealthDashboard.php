<?php

namespace App\Filament\Pages\Settings;

use App\Models\FailedSearchLog;
use App\Models\IndexNowPushLog;
use App\Models\NotFoundLog;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\SearchLog;
use App\Services\CoreWebVitalsService;
use App\Services\GoogleSearchConsoleService;
use App\Services\RedirectLoopDetector;
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
     * @return array{activeRedirects:int, loopCount:int, unresolved404s:int}
     */
    public function redirectHealth(): array
    {
        return [
            'activeRedirects' => Redirect::query()->active()->count(),
            'loopCount' => count(app(RedirectLoopDetector::class)->findAllLoops()),
            'unresolved404s' => NotFoundLog::query()->where('resolved', false)->count(),
        ];
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
