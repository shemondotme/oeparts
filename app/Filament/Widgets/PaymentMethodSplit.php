<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PaymentMethodSplit extends ChartWidget implements \App\Filament\Support\DrilldownContract
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Payment method usage statistics';
    }

    protected string $view = 'filament.widgets.chart-with-drilldown';

    protected ?string $heading = 'Payment Methods';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -24;

    protected static ?string $maxWidth = '1/3';

    public function getDrilldownUrl(): ?string
    {
        return PaymentResource::getUrl('index');
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $cached = $this->cachedWidgetData(function (): array {
            $data = Order::select('payment_method', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', $this->periodStart())
                ->groupBy('payment_method')
                ->get();

            return [
                'counts' => $data->pluck('count')->all(),
                'methods' => $data->map(fn ($row) => $row->payment_method->value)->all(),
            ];
        });

        $colors = [
            PaymentMethod::Card->value => '#0B3A68',
            PaymentMethod::BankTransfer->value => '#F59E0B',
        ];

        return [
            'datasets' => [
                [
                    'data' => $cached['counts'],
                    'backgroundColor' => array_map(fn (string $m) => $colors[$m] ?? '#94A3B8', $cached['methods']),
                    'borderWidth' => 2,
                    'borderColor' => '#FFFFFF',
                ],
            ],
            'labels' => array_map(
                fn (string $m) => PaymentMethod::tryFrom($m)?->label() ?? ucfirst($m),
                $cached['methods'],
            ),
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
                        'color' => '#64748b',
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
