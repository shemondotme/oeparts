<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\Widget;

class PendingOrdersKpi extends Widget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.pending-orders-kpi';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -35;

    public function getDescription(): ?string
    {
        return 'Pending orders count and average wait time';
    }

    protected function getViewData(): array
    {
        $d = $this->cachedWidgetData(function (): array {
            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
            $diffExpr = $driver === 'sqlite'
                ? "(julianday('now') - julianday(created_at)) * 1440"
                : 'TIMESTAMPDIFF(MINUTE, created_at, NOW())';
            $orders = Order::where('status', OrderStatus::Pending->value)
                ->selectRaw("COUNT(*) as count, COALESCE(AVG({$diffExpr}), 0) as avg_minutes")
                ->first();

            $count = (int) ($orders->count ?? 0);
            $avgMinutes = (int) round((float) ($orders->avg_minutes ?? 0));

            return [
                'count' => $count,
                'avgMinutes' => $avgMinutes,
                'delayedThreshold' => (int) settings('dashboard.pending_delayed_minutes', 120),
            ];
        });

        if ($this->loadFailed ?? false) {
            $d = ['count' => 0, 'avgMinutes' => 0, 'delayedThreshold' => 120];
        }

        $isDelayed = $d['avgMinutes'] > $d['delayedThreshold'];
        $d['status'] = $isDelayed ? 'Delayed' : 'On Track';
        $d['statusColor'] = $isDelayed ? 'var(--accent-danger)' : 'var(--accent-success)';

        if ($d['avgMinutes'] >= 2880) {
            $days = round($d['avgMinutes'] / 1440, 1);
            $d['waitLabel'] = $days . 'd avg';
        } elseif ($d['avgMinutes'] >= 60) {
            $hours = floor($d['avgMinutes'] / 60);
            $mins = $d['avgMinutes'] % 60;
            $d['waitLabel'] = $hours . 'h ' . $mins . 'm avg';
        } else {
            $d['waitLabel'] = $d['avgMinutes'] . 'm avg';
        }

        return $d;
    }
}
