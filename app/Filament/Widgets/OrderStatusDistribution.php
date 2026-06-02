<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStatusDistribution extends ChartWidget
{
    protected ?string $heading = 'Order Status Distribution';

    protected static ?int $sort = -11;

    protected static ?string $maxWidth = '1/3';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $data = Order::select('status', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('status')
            ->get();

        $colors = [
            'pending' => '#F59E0B',
            'paid' => '#3B82F6',
            'processing' => '#0B3A68',
            'shipped' => '#10B981',
            'delivered' => '#059669',
            'cancelled' => '#EF4444',
            'refund_requested' => '#F97316',
            'refunded' => '#6B7280',
        ];

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count'),
                    'backgroundColor' => $data->map(fn ($row) => $colors[$row->status] ?? '#94A3B8'),
                    'borderWidth' => 2,
                    'borderColor' => '#FFFFFF',
                ],
            ],
            'labels' => $data->map(fn ($row) => ucfirst(str_replace('_', ' ', $row->status))),
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
