<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\AbandonedCartResource;
use App\Models\AbandonedCart;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class CheckoutDropoffChart extends ChartWidget implements \App\Filament\Support\DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Conversion funnel from cart to completed order';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Checkout Funnel';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -27;

    protected static ?string $maxWidth = '1/2';

    public function getDrilldownUrl(): ?string
    {
        return AbandonedCartResource::getUrl('index');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        try {
            $funnel = $this->cachedWidgetData(function (): array {
                $start = $this->periodStart();

                return [
                    'cartCreated' => AbandonedCart::where('created_at', '>=', $start)->count(),
                    // 'abandoned' is not an OrderStatus case — cart abandonment is
                    // the delta between the first two bars; the last bar tracks
                    // cancellations.
                    'startedCheckout' => Order::where('created_at', '>=', $start)->count(),
                    'completed' => Order::where('created_at', '>=', $start)
                        ->whereIn('status', [
                            OrderStatus::Processing->value,
                            OrderStatus::Paid->value,
                            OrderStatus::Shipped->value,
                            OrderStatus::Delivered->value,
                        ])
                        ->count(),
                    'paid' => Order::where('created_at', '>=', $start)
                        ->whereIn('status', [
                            OrderStatus::Paid->value,
                            OrderStatus::Shipped->value,
                            OrderStatus::Delivered->value,
                        ])
                        ->count(),
                    'cancelled' => Order::where('created_at', '>=', $start)
                        ->where('status', OrderStatus::Cancelled->value)
                        ->count(),
                ];
            });
        } catch (\Exception $e) {
            report($e);
            $funnel = ['cartCreated' => 0, 'startedCheckout' => 0, 'completed' => 0, 'paid' => 0, 'cancelled' => 0];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => [$funnel['cartCreated'], $funnel['startedCheckout'], $funnel['completed'], $funnel['paid'], $funnel['cancelled']],
                    'backgroundColor' => [
                        'rgba(11, 58, 104, 0.85)',
                        'rgba(11, 58, 104, 0.70)',
                        'rgba(11, 58, 104, 0.55)',
                        'rgba(245, 158, 11, 0.85)',
                        'rgba(239, 68, 68, 0.70)',
                    ],
                    'borderColor' => [
                        '#0B3A68',
                        '#0B3A68',
                        '#0B3A68',
                        '#F59E0B',
                        '#EF4444',
                    ],
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['Cart Created', 'Started Checkout', 'Completed', 'Paid', 'Cancelled'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'font' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => [
                        'font' => ['family' => 'Geist Sans, sans-serif', 'size' => 11],
                        'color' => '#64748b',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'titleFont' => ['family' => 'Geist Sans, sans-serif', 'size' => 12, 'weight' => 'bold'],
                    'bodyFont' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 12],
                    'backgroundColor' => '#0f172a',
                    'titleColor' => '#f8fafc',
                    'bodyColor' => '#cbd5e1',
                    'borderColor' => '#1e293b',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'padding' => 10,
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
