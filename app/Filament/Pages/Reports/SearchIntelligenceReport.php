<?php

namespace App\Filament\Pages\Reports;

use App\Models\FailedSearchLog;
use App\Filament\Clusters\Reports;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SearchIntelligenceReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Search Intelligence';

    protected ?string $subheading = 'OEM queries, execution counts, and unresolved search logs.';

    protected string $view = 'filament.pages.reports.search-intelligence-report';

    public string $period = '30';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return 'Search Intelligence';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }


    public function getTotalSearches(): int
    {
        $start = Carbon::now()->subDays((int) $this->period);

        return \App\Models\SearchLog::where('created_at', '>=', $start)->count();
    }

    public function getUnresolvedSearches(): int
    {
        $start = Carbon::now()->subDays((int) $this->period);

        return FailedSearchLog::where('created_at', '>=', $start)
            ->where('inquiry_submitted', false)
            ->count();
    }

    public function getTopSearches(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        return \App\Models\SearchLog::where('created_at', '>=', $start)
            ->select('search_query', DB::raw('COUNT(*) as count'))
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->toArray();
    }

    public function getFailedQueries(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        return FailedSearchLog::where('created_at', '>=', $start)
            ->where('inquiry_submitted', false)
            ->select('search_query', DB::raw('COUNT(*) as count'))
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }
}
