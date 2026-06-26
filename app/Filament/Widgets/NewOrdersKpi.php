<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;

class NewOrdersKpi extends Widget
{
    use Concerns\HasWidgetRoles;
    use Concerns\HasDashboardPeriod;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.new-orders-kpi';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -36;

    public function getDescription(): ?string
    {
        return 'New order count with trend comparison';
    }

    protected function getViewData(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $start = $this->periodStart();
            $days = max(1, (int) $this->period);
            $prevStart = $start->copy()->subDays($days);

            $current = Order::where('created_at', '>=', $start)->count();
            $previous = Order::whereBetween('created_at', [$prevStart, $start])->count();

            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $hourExpr = $driver === 'sqlite'
                ? "CAST(strftime('%H', created_at) AS INTEGER) as hour"
                : 'HOUR(created_at) as hour';
            $hourly = Order::selectRaw("{$hourExpr}, COUNT(*) as count")
                ->where('created_at', '>=', $start)
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('count', 'hour')
                ->toArray();

            // 7-day daily bar counts (oldest → newest)
            $bars = collect(range(6, 0))->map(
                fn ($i) => Order::whereDate('created_at', now()->subDays($i))->count()
            )->values()->toArray();

            return [
                'count'     => $current,
                'previous'  => $previous,
                'hourly'    => $hourly,
                'bars'      => $bars,
                'threshold' => (int) settings('dashboard.orders_threshold', 50),
            ];
        });

        if ($this->loadFailed ?? false) {
            $d = ['count' => 0, 'previous' => 0, 'hourly' => [], 'threshold' => 50];
        }

        $trend = 'flat';
        $trendColor = 'var(--text-secondary)';

        if ($d['previous'] > 0) {
            $pct = round((($d['count'] - $d['previous']) / $d['previous']) * 100, 1);
            $trend = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
            $trendColor = $pct > 0 ? 'var(--accent-success)' : ($pct < 0 ? 'var(--accent-danger)' : 'var(--text-secondary)');
            $d['trendLabel'] = ($pct > 0 ? '+' : '') . number_format($pct, 1) . '%';
        } else {
            $d['trendLabel'] = $d['count'] > 0 ? 'New' : 'No data';
        }

        $d['trend'] = $trend;
        $d['trendColor'] = $trendColor;
        $d['exceedsThreshold'] = $d['count'] > $d['threshold'];

        return $d;
    }
}
