<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NewsletterGrowthWidget extends StatsOverviewWidget
{
    public function getDescription(): ?string
    {
        return 'Subscriber acquisition over time';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -16;

    protected ?string $heading = 'Newsletter Growth';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getStats(): array
    {
        $d = $this->cachedWidgetData(fn (): array => [
            'total' => NewsletterSubscriber::where('is_active', true)->count(),
            'inPeriod' => NewsletterSubscriber::where('subscribed_at', '>=', $this->periodStart())->count(),
            'thisWeek' => NewsletterSubscriber::where('subscribed_at', '>=', now()->startOfWeek())->count(),
            'unsubscribed' => NewsletterSubscriber::where('is_active', false)
                ->whereNotNull('unsubscribed_at')
                ->where('unsubscribed_at', '>=', $this->periodStart())
                ->count(),
        ]);

        return [
            Stat::make('Active Subscribers', number_format($d['total']))
                ->description("+{$d['inPeriod']} " . $this->periodLabel())
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('success')
                ->url(NewsletterSubscriberResource::getUrl('index')),
            Stat::make('New This Week', $d['thisWeek'])
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info')
                ->url(NewsletterSubscriberResource::getUrl('index')),
            Stat::make('Unsubscribed (' . $this->periodLabel() . ')', $d['unsubscribed'])
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color('warning')
                ->url($d['unsubscribed'] > 0 ? NewsletterSubscriberResource::getUrl('index') : null),
        ];
    }
}
