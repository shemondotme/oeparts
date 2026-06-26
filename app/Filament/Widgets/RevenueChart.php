<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\OrderResource;
use App\Filament\Support\DrilldownContract;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class RevenueChart extends ChartWidget implements DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Revenue trend over the selected period';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Revenue Trend';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    protected static ?int $sort = -38;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    // Default to 30-day view; synced with the segmented control
    public ?string $filter = '30';

    #[\Livewire\Attributes\Renderless]
    public function getPlaceholder(): string
    {
        return view('filament.widgets.chart-skeleton', ['heading' => $this->getHeading()])->render();
    }

    public function getDrilldownUrl(): ?string
    {
        return OrderResource::getUrl('index');
    }

    /** Segmented date-range control (Today / 7d / 30d / 90d / 1y). */
    protected function getFilters(): ?array
    {
        return [
            '1'   => 'Today',
            '7'   => '7d',
            '30'  => '30d',
            '90'  => '90d',
            '365' => '1y',
        ];
    }

    /** Sync global period and notify OrderVolumeChart when the filter changes. */
    public function updatedFilter(string $value): void
    {
        $this->period = $value;
        $this->dispatch('cc-date-range-changed', range: $value);
    }

    /** Keep filter in sync when the sibling chart (W8) drives the change. */
    #[\Livewire\Attributes\On('cc-date-range-changed')]
    public function onCcDateRangeChanged(string $range): void
    {
        if ($this->filter !== $range) {
            $this->filter = $range;
            $this->period = $range;
            $this->updateChartData();
        }
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions(chartOnly: true)];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = $this->periodStart();
        $end   = now();

        $cached = $this->cachedWidgetData(function () use ($start, $end): array {
            $paidStatuses = [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ];

            $data = Trend::query(Order::whereIn('status', $paidStatuses))
                ->between(start: $start, end: $end)
                ->perDay()
                ->sum('grand_total');

            return [
                'values' => $data->map(fn (TrendValue $v) => bcadd((string) $v->aggregate, '0', 2))->all(),
                'labels' => $data->map(fn (TrendValue $v) => $v->date)->all(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label'                  => 'Revenue (€)',
                    'data'                   => $cached['values'],
                    'borderColor'            => 'var(--aurora-violet)',
                    'backgroundColor'        => 'transparent',
                    'op_gradient'            => ['rgba(139,92,246,0.42)', 'rgba(139,92,246,0.01)'],
                    'fill'                   => true,
                    'tension'                => 0.4,
                    'pointRadius'            => 3,
                    'pointHoverRadius'       => 6,
                    'pointBackgroundColor'   => 'var(--aurora-violet)',
                    'pointBorderColor'       => 'var(--aurora-cyan)',
                    'pointBorderWidth'       => 2,
                    'pointHoverBorderWidth'  => 3,
                    'borderWidth'            => 2,
                ],
            ],
            'labels' => $cached['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'grid'  => ['display' => false],
                    'ticks' => [
                        'maxTicksLimit' => 7,
                        'font'          => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color'         => 'var(--color-text-muted)',
                    ],
                ],
                'y' => [
                    'grid'  => [
                        'color'       => 'var(--chart-grid)',
                        'borderDash'  => [4, 4],
                        'drawBorder'  => false,
                    ],
                    'ticks' => [
                        'callback' => 'function(v){return "€"+Number(v).toLocaleString("en-US",{minimumFractionDigits:0,maximumFractionDigits:0})}',
                        'font'     => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color'    => 'var(--color-text-muted)',
                        'maxTicksLimit' => 5,
                    ],
                ],
            ],
            'plugins' => [
                'legend'  => ['display' => false],
                'tooltip' => [
                    'titleFont'    => ['family' => 'Geist Sans, sans-serif', 'size' => 12, 'weight' => '600'],
                    'bodyFont'     => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 12],
                    'backgroundColor' => 'var(--chart-tooltip-bg)',
                    'titleColor'   => 'var(--chart-tooltip-text)',
                    'bodyColor'    => 'var(--chart-tooltip-text)',
                    'borderColor'  => 'var(--color-border-default)',
                    'borderWidth'  => 1,
                    'cornerRadius' => 8,
                    'padding'      => 10,
                    'callbacks'    => [
                        'label' => 'function(ctx){return "€"+Number(ctx.parsed.y).toLocaleString("en-US",{minimumFractionDigits:2})}',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
