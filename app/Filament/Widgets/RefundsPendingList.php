<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RefundsPendingList extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Refunds Pending';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -32;

    public function getDescription(): ?string
    {
        return 'Orders with pending refund requests';
    }

    protected function getTableHeading(): string
    {
        $count = Order::where('status', OrderStatus::RefundRequested->value)->count();

        return 'Refunds Pending' . ($count > 0 ? " ({$count})" : '');
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\RefundRequestResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->where('status', OrderStatus::RefundRequested->value)
                    ->with(['user'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—'),
                TextColumn::make('created_at')
                    ->label('Days Open')
                    ->badge()
                    ->icon('heroicon-m-clock')
                    ->getStateUsing(fn (Order $record): string => (int) $record->created_at->diffInDays(now()) . ' days')
                    ->color(function (Order $record): string {
                        $days = (int) $record->created_at->diffInDays(now());
                        if ($days < 14) {
                            return 'success';
                        }
                        if ($days < 27) {
                            return 'warning';
                        }
                        return 'danger';
                    }),
                TextColumn::make('grand_total')
                    ->label('Amount')
                    ->formatStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->color('danger')
                    ->description('refund requested')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('Refund exposure')
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Process Refund')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(fn (Order $record): string => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->recordClasses(function (Order $record): ?string {
                $days = (int) $record->created_at->diffInDays(now());
                if ($days >= 27) {
                    return 'op-row-critical';
                }
                if ($days >= 14) {
                    return 'op-row-warn';
                }
                return null;
            })
            ->striped()
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-currency-euro')
            ->emptyStateHeading('No pending refunds')
            ->emptyStateDescription('All refund requests have been processed.');
    }
}
