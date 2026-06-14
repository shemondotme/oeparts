<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

use App\Filament\Resources\OrderResource;

class RevenueChart extends ChartWidget implements \App\Filament\Support\DrilldownContract
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

    protected ?string $heading = 'Revenue Trend over Time';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    #[\Livewire\Attributes\Renderless]
    public function getPlaceholder(): string
    {
        return view('filament.widgets.chart-skeleton', ['heading' => $this->getHeading()])->render();
    }

    protected static ?int $sort = -38;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    #[\Livewire\Attributes\On('date-range-changed')]
    public function onDateRangeChanged(?string $dateFrom, ?string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getDrilldownUrl(): ?string
    {
        return OrderResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('date_filter')
                ->label($this->dateFrom ? 'Custom Range' : 'Filter Dates')
                ->icon('heroicon-o-calendar-days')
                ->color($this->dateFrom ? 'primary' : 'gray')
                ->size(\Filament\Support\Enums\Size::Small)
                ->button()
                ->modalHeading('Custom Date Range')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('date_from')
                        ->label('From')
                        ->default($this->dateFrom)
                        ->maxDate(today()),
                    \Filament\Forms\Components\DatePicker::make('date_to')
                        ->label('To')
                        ->default($this->dateTo)
                        ->maxDate(today()),
                ])
                ->action(function (array $data): void {
                    $this->dateFrom = $data['date_from'] ?? null;
                    $this->dateTo   = $data['date_to'] ?? null;
                    $this->dispatch('date-range-changed', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
                }),
            $this->getExportActions(chartOnly: true),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = $this->dateFrom
            ? \Carbon\Carbon::parse($this->dateFrom)->startOfDay()
            : $this->periodStart();
        $end = $this->dateTo
            ? \Carbon\Carbon::parse($this->dateTo)->endOfDay()
            : now();

        $fetch = function () use ($start, $end): array {
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
        };

        $cached = ($this->dateFrom || $this->dateTo) ? $fetch() : $this->cachedWidgetData($fetch);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (€)',
                    'data' => $cached['values'],
                    'borderColor' => '#8B5CF6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.18)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => '#8B5CF6',
                    'pointBorderColor' => '#22D3EE',
                    'pointBorderWidth' => 3,
                    'pointHoverBackgroundColor' => '#A78BFA',
                    'pointHoverBorderColor' => '#67E8F9',
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
                    'grid' => ['display' => false],
                    'ticks' => [
                        'maxTicksLimit' => 7,
                        'font' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => '#94a3b8',
                    ],
                ],
                'y' => [
                    'grid' => ['color' => 'rgba(10, 18, 40, 0.06)', 'drawBorder' => false],
                    'ticks' => [
                        'callback' => 'function(value) { return "€" + value; }',
                        'font' => ['family' => 'Geist Mono, JetBrains Mono, monospace', 'size' => 11],
                        'color' => '#94a3b8',
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
                    'callbacks' => [
                        'label' => 'function(ctx) { return "€" + Number(ctx.parsed.y).toLocaleString("en-US", {minimumFractionDigits: 2}); }',
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
