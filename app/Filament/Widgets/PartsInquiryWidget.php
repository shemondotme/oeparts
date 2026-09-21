<?php

namespace App\Filament\Widgets;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource;
use App\Filament\Widgets\Concerns\HasWidgetRoles;
use App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
use App\Models\PartInquiry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartsInquiryWidget extends StatsOverviewWidget
{
    use HasWidgetRoles;
    use InteractsWithDashboardCache;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -19;

    // Full-width: a 3-KPI StatsOverview reads better as a horizontal strip than
    // as 3 stats stacked vertically in a half-width column next to a table.
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $d = $this->cachedWidgetData(fn (): array => [
            'today' => PartInquiry::whereDate('created_at', now())->count(),
            'pending' => PartInquiry::where('status', PartInquiryStatus::New->value)->count(),
            'thisWeek' => PartInquiry::where('created_at', '>=', now()->startOfWeek())->count(),
            'responded' => PartInquiry::whereNotNull('admin_note')
                ->where('created_at', '>=', now()->startOfWeek())->count(),
            'totalThisWeek' => PartInquiry::where('created_at', '>=', now()->startOfWeek())->count(),
            'sparkline' => collect(range(6, 0))->map(
                fn ($i) => PartInquiry::whereDate('created_at', now()->subDays($i))->count()
            )->all(),
        ]);

        $responseRate = $d['totalThisWeek'] > 0
            ? round(($d['responded'] / max($d['totalThisWeek'], 1)) * 100)
            : 0;

        $inquiriesUrl = PartInquiryResource::getUrl('index');

        return [
            Stat::make('Today', $d['today'])
                ->description('Inquiries received')
                ->descriptionIcon('heroicon-m-envelope')
                ->chart($d['sparkline'])
                ->color('primary')
                ->url($inquiriesUrl),
            Stat::make('Pending', $d['pending'])
                ->description('Awaiting response')
                ->descriptionIcon('heroicon-m-clock')
                ->color($d['pending'] > 0 ? 'warning' : 'success')
                ->url($inquiriesUrl),
            Stat::make('This Week', $d['thisWeek'])
                ->description("Response rate: {$responseRate}%")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($responseRate >= 90 ? 'success' : 'warning')
                ->url($inquiriesUrl),
        ];
    }
}
