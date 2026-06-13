<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStatusDistribution extends ChartWidget implements \App\Filament\Support\DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Breakdown of current order statuses';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Order Status Distribution';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -25;

    protected static bool $isLazy = true;

    #[\Livewire\Attributes\Renderless]
    public function getPlaceholder(): string
    {
        return view('filament.widgets.chart-skeleton', ['heading' => $this->getHeading()])->render();
    }

    protected static ?string $maxWidth = '1/3';

    public function getDrilldownUrl(): ?string
    {
        return OrderResource::getUrl('index');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $cached = $this->cachedWidgetData(function (): array {
            $data = Order::select('status', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', $this->periodStart())
                ->groupBy('status')
                ->get();

            return [
                'counts' => $data->pluck('count')->all(),
                'statuses' => $data->map(fn ($row) => $row->status->value)->all(),
            ];
        });

        $colors = [
            OrderStatus::Pending->value => '#F59E0B',
            OrderStatus::Paid->value => '#22D3EE',
            OrderStatus::Processing->value => '#8B5CF6',
            OrderStatus::Shipped->value => '#10B981',
            OrderStatus::Delivered->value => '#34D399',
            OrderStatus::Cancelled->value => '#F43F5E',
            OrderStatus::RefundRequested->value => '#FB7185',
            OrderStatus::Refunded->value => '#94A3B8',
        ];

        return [
            'datasets' => [
                [
                    'data' => $cached['counts'],
                    'backgroundColor' => array_map(fn (string $s) => $colors[$s] ?? '#94A3B8', $cached['statuses']),
                    'borderWidth' => 2,
                    'borderColor' => '#1e293b',
                ],
            ],
            'labels' => array_map(fn (string $s) => ucfirst(str_replace('_', ' ', $s)), $cached['statuses']),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 12,
                        'usePointStyle' => true,
                        'pointStyleWidth' => 10,
                        'font' => ['family' => 'Geist Sans, sans-serif', 'size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
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
