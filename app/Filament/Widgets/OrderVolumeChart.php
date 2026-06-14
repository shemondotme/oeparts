<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class OrderVolumeChart extends ChartWidget
{
    use Concerns\HasDashboardPeriod;
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected ?string $heading = 'Order Volume';

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    protected static ?int $sort = -34;

    public function getDescription(): ?string
    {
        return 'Daily order count over the selected period';
    }

    #[\Livewire\Attributes\On('date-range-changed')]
    public function onDateRangeChanged(?string $dateFrom, ?string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
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
        return 'bar';
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
            $data = Trend::query(Order::query())
                ->between(start: $start, end: $end)
                ->perDay()
                ->count();

            return [
                'values' => $data->map(fn (TrendValue $v) => $v->aggregate)->all(),
                'labels' => $data->map(fn (TrendValue $v) => $v->date)->all(),
            ];
        };

        $cached = ($this->dateFrom || $this->dateTo) ? $fetch() : $this->cachedWidgetData($fetch);

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $cached['values'],
                    'backgroundColor' => 'rgba(99, 102, 241, 0.80)',
                    'borderColor' => '#6366F1',
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'hoverBackgroundColor' => 'rgba(139, 92, 246, 0.90)',
                ],
            ],
            'labels' => $cached['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                    'grid' => [
                        'color' => 'var(--chart-grid)',
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'backgroundColor' => 'var(--chart-tooltip-bg)',
                    'titleColor' => 'var(--chart-tooltip-text)',
                    'bodyColor' => 'var(--chart-tooltip-text)',
                    'cornerRadius' => 8,
                    'padding' => 12,
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
