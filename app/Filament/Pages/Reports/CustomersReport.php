<?php

namespace App\Filament\Pages\Reports;

use App\Models\User;
use App\Models\Order;
use App\Filament\Clusters\Reports;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class CustomersReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Customers Report';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action('exportCsv'),
        ];
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $start = Carbon::now()->subDays((int) $this->period);

        $data = User::leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->where('orders.created_at', '>=', $start)
            ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(orders.id) as order_count'), DB::raw('SUM(orders.grand_total) as total_spent'))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_spent')
            ->get();

        $filename = 'customers-report-' . now()->format('Y-m-d') . '.csv';

        return Response::stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Customer ID', 'Name', 'Email', 'Orders', 'Total Spent']);
            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->name,
                    $row->email,
                    $row->order_count,
                    number_format((float) bcadd((string) ($row->total_spent ?? '0'), '0', 2), 2, '.', ''),
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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
            $c['total_spent'] = number_format((float) bcadd((string) ($c['total_spent'] ?? '0'), '0', 2), 2, '.', '');
            return $c;
        }, $customers);
    }
}
