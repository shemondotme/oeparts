<?php

namespace App\Filament\Pages\Reports;

use App\Models\Order;
use App\Filament\Clusters\Reports;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class SalesReport extends Page
{
    protected static ?string $cluster = Reports::class;

    protected static ?string $title = 'Sales Report';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && ($user->hasRole('super_admin') || $user->hasRole('admin'));
    }

    protected ?string $subheading = 'Store sales, order counts, and revenue performance.';

    protected string $view = 'filament.pages.reports.sales-report';

    public string $period = '30';

    protected static bool $shouldRegisterNavigation = true;

    public static function getNavigationLabel(): string
    {
        return 'Sales Report';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
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

        $data = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $start)
            ->select(
                'orders.id as order_id',
                'orders.created_at',
                'orders.status',
                'orders.grand_total',
                'order_items.oem_number_snapshot',
                'order_items.quantity',
                'order_items.total_price'
            )
            ->orderBy('orders.created_at', 'desc')
            ->get();

        $filename = 'sales-report-' . now()->format('Y-m-d') . '.csv';

        return Response::stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order ID', 'Date', 'Status', 'Grand Total', 'OEM Number', 'Qty', 'Line Total']);
            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->order_id,
                    $row->created_at?->format('Y-m-d H:i'),
                    $row->status instanceof \BackedEnum ? $row->status->value : $row->status,
                    number_format((float) bcadd((string) $row->grand_total, '0', 2), 2, '.', ''),
                    $row->oem_number_snapshot,
                    $row->quantity,
                    number_format((float) bcadd((string) $row->total_price, '0', 2), 2, '.', ''),
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * KPIs, chart and top-products are now native Filament widgets
     * (App\Filament\Widgets\Reports\Sales*), rendered by the page view and fed
     * the selected $period. CSV export stays here as a header action.
     */
}
