<?php

namespace App\Filament\Pages\Reports;

use App\Models\User;
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
                ->action(fn () => $this->exportCsv()),
        ];
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $start = ($this->period === '1' ? Carbon::today() : Carbon::now()->subDays((int) $this->period));

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

    // KPIs, growth chart and top-customers are native Filament widgets
    // (App\Filament\Widgets\Reports\Customers*), rendered by the page view.
}
