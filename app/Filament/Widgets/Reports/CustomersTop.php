<?php

namespace App\Filament\Widgets\Reports;

use App\Models\User;
use Carbon\Carbon;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class CustomersTop extends TableWidget
{
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected static ?string $heading = 'Top Customers';

    public function table(Table $table): Table
    {
        $start = $this->periodStart();

        return $table
            ->query(
                User::query()
                    ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
                    ->where('orders.created_at', '>=', $start)
                    ->select(
                        'users.id',
                        'users.name',
                        'users.email',
                        DB::raw('COUNT(orders.id) as order_count'),
                        DB::raw('SUM(orders.grand_total) as total_spent'),
                    )
                    ->groupBy('users.id', 'users.name', 'users.email')
                    ->orderByDesc('total_spent')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Customer')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (User $record): string => (string) $record->email),
                TextColumn::make('order_count')
                    ->label('Orders')
                    ->alignCenter()
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold),
                TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->formatStateUsing(fn ($state): string => format_money($state))
                    ->alignEnd()
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Bold)
                    ->color('success'),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('No customer orders')
            ->emptyStateDescription('No customers placed orders in this period.');
    }
}
