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

    // Full-width: it's the trailing (odd) widget in the Catalog & Search group,
    // so half-width left it alone with an empty right column. Full width also
    // lays its 3 stats out horizontally.
    protected int|string|array $columnSpan = 'full';

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

        if ($d['total'] === 0) {
            // Three stats (not one) so the full-width widget fills its row:
            // StatsOverviewWidget lays <3 stats in a 3-column grid, leaving a
            // single stat occupying only 1/3 of the width.
            return [
                Stat::make('Active Subscribers', '0')
                    ->description('Launch your first campaign')
                    ->descriptionIcon('heroicon-o-envelope')
                    ->color('gray'),
                Stat::make('New This Week', '0')
                    ->description('No sign-ups yet')
                    ->descriptionIcon('heroicon-o-calendar')
                    ->color('gray'),
                Stat::make('Unsubscribed', '0')
                    ->description('Nothing to report')
                    ->descriptionIcon('heroicon-o-arrow-trending-down')
                    ->color('gray'),
            ];
        }

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
                ->description('Rate: ' . ($d['inPeriod'] > 0 ? round(($d['unsubscribed'] / max($d['inPeriod'], 1)) * 100, 1) . '%' : '0%'))
                ->descriptionIcon('heroicon-o-arrow-trending-down')
                ->color($d['unsubscribed'] > 0 ? 'warning' : 'success')
                ->url($d['unsubscribed'] > 0 ? NewsletterSubscriberResource::getUrl('index') : null),
        ];
    }
}
