<?php

namespace App\Filament\Widgets;

use App\Models\AbandonedCart;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class CheckoutDropoffChart extends ChartWidget
{
    protected ?string $heading = 'Checkout Funnel — Last 30 Days';

    protected static ?int $sort = -13;

    protected static ?string $maxWidth = '1/2';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = now()->subDays(30);

        $cartCreated = Order::where('created_at', '>=', $start)->count();

        $startedCheckout = Order::where('created_at', '>=', $start)
            ->where('status', '!=', 'abandoned')
            ->count();

        $completed = Order::where('created_at', '>=', $start)
            ->whereIn('status', ['processing', 'paid', 'shipped', 'delivered'])
            ->count();

        $paid = Order::where('created_at', '>=', $start)
            ->whereIn('status', ['paid', 'shipped', 'delivered'])
            ->count();

        $abandoned = Order::where('created_at', '>=', $start)
            ->where('status', 'abandoned')
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => [$cartCreated, $startedCheckout, $completed, $paid, $abandoned],
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
            'labels' => ['Cart Created', 'Started Checkout', 'Completed', 'Paid', 'Abandoned'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['font' => ['size' => 11]],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['font' => ['size' => 11]],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
