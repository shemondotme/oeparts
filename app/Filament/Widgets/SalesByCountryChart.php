<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesByCountryChart extends ChartWidget
{
    protected ?string $heading = 'Sales by Country (30 days)';

    protected static ?int $sort = -12;

    protected static ?string $maxWidth = '1/3';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $data = Order::whereIn('status', $paidStatuses)
            ->where('created_at', '>=', now()->subDays(30))
            ->select('shipping_country_code', DB::raw('COUNT(*) as order_count'))
            ->groupBy('shipping_country_code')
            ->orderByDesc('order_count')
            ->limit(8)
            ->get();

        $countryNames = [
            'DE' => 'Germany',
            'FR' => 'France',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'PL' => 'Poland',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'AT' => 'Austria',
            'CZ' => 'Czech Republic',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PT' => 'Portugal',
            'IE' => 'Ireland',
            'LU' => 'Luxembourg',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $data->pluck('order_count'),
                    'backgroundColor' => 'rgba(11, 58, 104, 0.75)',
                    'borderColor' => '#0B3A68',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->map(fn ($row) => $countryNames[$row->shipping_country_code] ?? $row->shipping_country_code),
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
