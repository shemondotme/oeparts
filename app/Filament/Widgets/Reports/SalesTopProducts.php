<?php

namespace App\Filament\Widgets\Reports;

use App\Models\OrderItem;
use Carbon\Carbon;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/**
 * Native top-selling-products table for the Sales report (was a hand-rolled
 * HTML <table>). Reads the report page's selected $period, passed in on mount.
 */
class SalesTopProducts extends TableWidget
{
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected static ?string $heading = 'Top Selling Products';

    public function table(Table $table): Table
    {
        $start = $this->periodStart();

        return $table
            ->query(
                OrderItem::query()
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.created_at', '>=', $start)
                    ->select(
                        DB::raw('MIN(order_items.id) as id'),
                        DB::raw('COALESCE(order_items.oem_number_snapshot, order_items.manufacturer_snapshot, CAST(order_items.product_id AS CHAR)) as name'),
                        DB::raw('SUM(order_items.quantity) as total_qty'),
                        DB::raw('SUM(order_items.total_price) as total_revenue'),
                    )
                    ->groupBy('order_items.product_id', 'order_items.oem_number_snapshot', 'order_items.manufacturer_snapshot')
                    ->orderByDesc('total_revenue')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('total_qty')
                    ->label('Qty Sold')
                    ->numeric()
                    ->alignCenter()
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state): string => format_money($state))
                    ->alignEnd()
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->color('success'),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->emptyStateHeading('No sales data')
            ->emptyStateDescription('No sales found for this period.');
    }
}
