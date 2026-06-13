<?php

namespace App\Filament\Widgets;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartsInquiryWidget extends StatsOverviewWidget
{
    public function getDescription(): ?string
    {
        return 'Incoming part requests from customers';
    }

    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -19;

    protected ?string $heading = 'Parts Inquiries';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getStats(): array
    {
        $d = $this->cachedWidgetData(fn (): array => [
            'today' => \App\Models\PartInquiry::whereDate('created_at', now())->count(),
            'pending' => \App\Models\PartInquiry::where('status', PartInquiryStatus::New->value)->count(),
            'thisWeek' => \App\Models\PartInquiry::where('created_at', '>=', now()->startOfWeek())->count(),
        ]);

        $today = $d['today'];
        $pending = $d['pending'];
        $thisWeek = $d['thisWeek'];

        return [
            Stat::make('Today\'s Inquiries', $today)
                ->description('Pending: ' . $pending)
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('info')
                ->url(PartInquiryResource::getUrl('index', ['tableFilters' => ['status' => ['value' => PartInquiryStatus::New->value]]])),
            Stat::make('This Week', $thisWeek)
                ->descriptionIcon('heroicon-o-calendar')
                ->color('success')
                ->url(PartInquiryResource::getUrl('index')),
        ];
    }
}
