<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RefundsPendingList extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Refunds Pending';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -32;

    public function getDescription(): ?string
    {
        return 'Orders with pending refund requests';
    }

    protected function getExportHeaders(): array
    {
        return ['Order #', 'Customer', 'Amount', 'Days Open'];
    }

    protected function getExportRows(): iterable
    {
        return Order::query()
            ->where('status', OrderStatus::RefundRequested->value)
            ->with(['user'])
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                $order->order_number,
                $order->shipping_name ?? $order->user?->name ?? $order->guest_email ?? '—',
                format_money($order->grand_total),
                (int) $order->created_at->diffInDays(now()) . ' days',
            ]);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            $this->getExportActions(),
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
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                    ->limit(20),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Amount')
                    ->formatStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->fontMono()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Days Open')
                    ->badge()
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
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Process Refund')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->url(fn (Order $record): string => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-currency-euro')
            ->emptyStateHeading('No pending refunds')
            ->emptyStateDescription('All refund requests have been processed.');
    }
}
