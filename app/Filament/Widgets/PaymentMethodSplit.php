<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PaymentMethodSplit extends ChartWidget
{
    protected ?string $heading = 'Payment Methods (30 days)';

    protected static ?int $sort = -10;

    protected static ?string $maxWidth = '1/3';

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $data = Order::select('payment_method', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('payment_method')
            ->get();

        $colors = [
            'card' => '#0B3A68',
            'bank_transfer' => '#F59E0B',
        ];

        $labels = [
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
        ];

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count'),
                    'backgroundColor' => $data->map(fn ($row) => $colors[$row->payment_method] ?? '#94A3B8'),
                    'borderWidth' => 2,
                    'borderColor' => '#FFFFFF',
                ],
            ],
            'labels' => $data->map(fn ($row) => $labels[$row->payment_method] ?? ucfirst($row->payment_method)),
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
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
