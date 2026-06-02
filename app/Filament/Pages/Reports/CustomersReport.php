<?php

namespace App\Filament\Pages\Reports;

use App\Models\User;
use App\Models\Order;
use App\Filament\Clusters\Reports;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomersReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Customers Report';

    protected ?string $subheading = 'Customer registrations, repeats, and spending analytics.';

    protected string $view = 'filament.pages.reports.customers-report';

    public string $period = '30';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return 'Customers Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }


    public function getTotalCustomers(): int
    {
        return User::count();
    }

    public function getNewCustomers(): int
    {
        $start = Carbon::now()->subDays((int) $this->period);

        return User::where('created_at', '>=', $start)->count();
    }

    public function getRepeatCustomers(): int
    {
        return DB::table('orders')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    public function getCustomerGrowth(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $data = User::where('created_at', '>=', $start)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return [
            'labels' => array_keys($data),
            'values' => array_values($data),
        ];
    }

    public function getTopCustomers(): array
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $customers = User::leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.created_at', '>=', $start)
            ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(orders.id) as order_count'), DB::raw('SUM(orders.grand_total) as total_spent'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->toArray();

        return array_map(function ($c) {
            $c['total_spent'] = number_format((float) ($c['total_spent'] ?? 0), 2, '.', '');
            return $c;
        }, $customers);
    }
}
