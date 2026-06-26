<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Concerns\HasWidgetExport;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AwaitingConfirmationList extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Awaiting Confirmation';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -33;

    public function getDescription(): ?string
    {
        return 'Orders pending admin confirmation';
    }

    protected function getExportHeaders(): array
    {
        return ['Order #', 'Customer', 'Status', 'Total', 'Placed'];
    }

    protected function getExportRows(): iterable
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['user'])
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                $order->order_number,
                $order->shipping_name ?? $order->user?->name ?? $order->guest_email ?? '—',
                $order->status instanceof OrderStatus ? $order->status->name : (string) $order->status,
                format_money($order->grand_total),
                $order->created_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])
                    ->where('created_at', '>=', now()->subDays(7))
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
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => $state instanceof OrderStatus ? $state->color() : 'gray'),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->fontMono(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waiting')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        app(OrderService::class)->transitionStatus(
                            $record,
                            OrderStatus::Shipped,
                            'Approved via Awaiting Confirmation widget.',
                            auth('admin')->id(),
                        );

                        Notification::make()
                            ->title('Order approved')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        app(OrderService::class)->transitionStatus(
                            $record,
                            OrderStatus::Cancelled,
                            'Rejected via Awaiting Confirmation widget.',
                            auth('admin')->id(),
                        );

                        Notification::make()
                            ->title('Order cancelled')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('All clear')
            ->emptyStateDescription('No orders awaiting confirmation in the last 7 days.');
    }
}
