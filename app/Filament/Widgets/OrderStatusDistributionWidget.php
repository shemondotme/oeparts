<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\OrderResource;
use App\Filament\Support\DrilldownContract;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusDistributionWidget extends ChartWidget implements DrilldownContract
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Order counts by status for the selected period';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Order Status';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    protected static ?int $sort = -24;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    #[\Livewire\Attributes\Renderless]
    public function getPlaceholder(): string
    {
        return view('filament.widgets.chart-skeleton', ['heading' => $this->getHeading()])->render();
    }

    public function getDrilldownUrl(): ?string
    {
        return OrderResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions(chartOnly: true)];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();

        $cached = $this->cachedWidgetData(function () use ($start): array {
            $counts = Order::where('created_at', '>=', $start)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            $statuses = OrderStatus::cases();
            $labels   = [];
            $values   = [];
            $colors   = [];

            $colorMap = [
                OrderStatus::Pending->value         => 'var(--aurora-amber)',
                OrderStatus::Paid->value            => 'var(--aurora-sky)',
                OrderStatus::Processing->value      => 'var(--aurora-indigo)',
                OrderStatus::Shipped->value         => 'var(--aurora-teal)',
                OrderStatus::Delivered->value       => 'var(--aurora-green)',
                OrderStatus::Cancelled->value       => 'var(--aurora-red)',
                OrderStatus::RefundRequested->value => 'var(--aurora-orange)',
                OrderStatus::Refunded->value        => 'var(--color-text-muted)',
            ];

            foreach ($statuses as $status) {
                $count = $counts[$status->value] ?? 0;
                if ($count === 0) {
                    continue;
                }
                $labels[] = $status->label();
                $values[] = $count;
                $colors[] = $colorMap[$status->value] ?? 'var(--color-text-muted)';
            }

            return compact('labels', 'values', 'colors');
        });

        return [
            'datasets' => [
                [
                    'data'                 => $cached['values'],
                    'backgroundColor'      => $cached['colors'],
                    'borderColor'          => 'var(--surface-card)',
                    'borderWidth'          => 2,
                    'hoverBorderWidth'     => 0,
                    'hoverOffset'          => 6,
                ],
            ],
            'labels' => $cached['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'right',
                    'labels'   => [
                        'font'        => ['family' => 'Geist Sans, sans-serif', 'size' => 12],
                        'color'       => 'var(--color-text-secondary)',
                        'padding'     => 12,
                        'boxWidth'    => 12,
                        'boxHeight'   => 12,
                        'borderRadius' => 3,
                        'useBorderRadius' => true,
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'var(--chart-tooltip-bg)',
                    'titleColor'      => 'var(--chart-tooltip-text)',
                    'bodyColor'       => 'var(--chart-tooltip-text)',
                    'borderColor'     => 'var(--color-border-default)',
                    'borderWidth'     => 1,
                    'cornerRadius'    => 8,
                    'padding'         => 10,
                    'callbacks'       => [
                        'label' => 'function(ctx){return " "+ctx.label+": "+ctx.parsed+" orders"}',
                    ],
                ],
            ],
            'cutout'             => '65%',
            'maintainAspectRatio' => false,
        ];
    }
}
