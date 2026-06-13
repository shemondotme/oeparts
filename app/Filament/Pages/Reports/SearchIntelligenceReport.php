<?php

namespace App\Filament\Pages\Reports;

use App\Models\FailedSearchLog;
use App\Filament\Clusters\Reports;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class SearchIntelligenceReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Search Intelligence';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    protected ?string $subheading = 'OEM queries, execution counts, and unresolved search logs.';

    protected string $view = 'filament.pages.reports.search-intelligence-report';

    public string $period = '30';

    public bool $showTable = false;

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return 'Search Intelligence';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('exportCsv'),
        ];
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $data = \App\Models\SearchLog::where('created_at', '>=', $start)
            ->select('search_query', DB::raw('COUNT(*) as count'))
            ->groupBy('search_query')
            ->orderByDesc('count')
            ->get();

        $filename = 'search-intelligence-' . now()->format('Y-m-d') . '.csv';

        return Response::stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Search Query', 'Count']);
            foreach ($data as $row) {
                fputcsv($handle, [$row->search_query, $row->count]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function toggleView(): void
    {
        $this->showTable = !$this->showTable;
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
