<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesByCountryChart extends ChartWidget implements \App\Filament\Support\DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Geographic distribution of sales';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Sales by Country';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -26;

    protected static ?string $maxWidth = '1/3';

    public function getDrilldownUrl(): ?string
    {
        return OrderResource::getUrl('index');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $cached = $this->cachedWidgetData(function (): array {
            $paidStatuses = [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ];

            $data = Order::whereIn('status', $paidStatuses)
                ->where('created_at', '>=', $this->periodStart())
                ->select('shipping_country_code', DB::raw('COUNT(*) as order_count'))
                ->groupBy('shipping_country_code')
                ->orderByDesc('order_count')
                ->limit(8)
                ->get();

            return [
                'values' => $data->pluck('order_count')->all(),
                'codes' => $data->pluck('shipping_country_code')->all(),
            ];
        });

        $countryNames = config('countries', []);

        $labels = array_map(
            fn (?string $code) => $code ? ($countryNames[$code] ?? $code) : '—',
            $cached['codes'],
        );

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $cached['values'],
                    'backgroundColor' => 'function(ctx){const c=ctx.chart.ctx;const g=c.createLinearGradient(0,0,400,0);g.addColorStop(0,"rgba(139,92,246,0.7)");g.addColorStop(0.5,"rgba(34,211,238,0.7)");g.addColorStop(1,"rgba(16,185,129,0.7)");return g;}',
                    'hoverBackgroundColor' => 'function(ctx){const c=ctx.chart.ctx;const g=c.createLinearGradient(0,0,400,0);g.addColorStop(0,"rgba(167,139,250,0.85)");g.addColorStop(0.5,"rgba(103,232,249,0.85)");g.addColorStop(1,"rgba(52,211,153,0.85)");return g;}',
                    'borderColor' => '#22D3EE',
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
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
