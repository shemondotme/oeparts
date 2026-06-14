<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\Widget;

class RevenueKpi extends Widget
{
    use Concerns\HasWidgetRoles;
    use Concerns\HasDashboardPeriod;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.revenue-kpi';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -37;

    public function getDescription(): ?string
    {
        return "Today's revenue with trend comparison";
    }

    protected function getViewData(): array
    {
        $paidStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $d = $this->cachedWidgetData(function () use ($paidStatuses): array {
            $start = $this->periodStart();
            $days = max(1, (int) $this->period);
            $prevStart = $start->copy()->subDays($days);

            $current = Order::whereIn('status', $paidStatuses)
                ->where('created_at', '>=', $start)
                ->sum('grand_total');

            $previous = Order::whereIn('status', $paidStatuses)
                ->whereBetween('created_at', [$prevStart, $start])
                ->sum('grand_total');

            return [
                'current' => (string) $current,
                'previous' => (string) $previous,
                'hasData' => bccomp((string) $current, '0', 2) === 1 || bccomp((string) $previous, '0', 2) === 1,
            ];
        });

        if ($this->loadFailed ?? false) {
            $d = ['current' => '0.00', 'previous' => '0.00', 'hasData' => false];
        }

        $trend = 'flat';
        $trendColor = 'var(--text-secondary)';

        if (bccomp($d['previous'], '0', 2) === 1) {
            $diff = bcsub($d['current'], $d['previous'], 4);
            $ratio = bcdiv($diff, $d['previous'], 4);
            $pct = (float) bcmul($ratio, '100', 1);

            if ($pct > 0) {
                $trend = 'up';
                $trendColor = 'var(--accent-success)';
            } elseif ($pct < 0) {
                $trend = 'down';
                $trendColor = 'var(--accent-danger)';
            }

            $d['trendLabel'] = ($pct > 0 ? '+' : '') . number_format($pct, 1) . '%';
        } else {
            $d['trendLabel'] = bccomp($d['current'], '0', 2) === 1 ? 'New' : 'No data';
        }

        $d['trend'] = $trend;
        $d['trendColor'] = $trendColor;
        $d['periodLabel'] = $this->periodLabel();

        return $d;
    }
}
