<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Notifications\Notification;
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

class AwaitingConfirmationList extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Awaiting Confirmation';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -33;

    public function getDescription(): ?string
    {
        return 'Paid orders awaiting fulfilment action';
    }

    protected function getTableHeading(): string
    {
        $count = Order::whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return 'Awaiting Confirmation' . ($count > 0 ? " ({$count})" : '');
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\OrderResource::getUrl('index')),
        ];
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
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->icon(fn (mixed $state): string => ($state instanceof OrderStatus ? $state->value : $state) === 'processing' ? 'heroicon-m-cog-6-tooth' : 'heroicon-m-banknotes')
                    ->color(fn (mixed $state): string => $state instanceof OrderStatus ? $state->color() : 'gray'),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Order $record): string => $record->created_at?->diffForHumans() ?? '—')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('Pending value')
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),
            ])
            ->actions([
                // Deliberately narrow: paid orders can be moved into processing
                // here; shipping (needs a tracking number) and cancelling a paid
                // order (refund territory) stay on the order page's guarded flows.
                Tables\Actions\Action::make('start_processing')
                    ->label('Start Processing')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('info')
                    ->size('sm')
                    ->authorize('update')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Paid)
                    ->requiresConfirmation()
                    ->modalDescription('Move this paid order into processing. The customer is notified by email.')
                    ->action(function (Order $record): void {
                        try {
                            app(OrderService::class)->transitionStatus(
                                $record,
                                OrderStatus::Processing,
                                'Started processing via Awaiting Confirmation widget.',
                                auth('admin')->id(),
                            );
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Transition not allowed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Order is now processing')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->size('sm')
                    ->url(fn (Order $record): string => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->recordClasses(fn (Order $record): ?string => (int) $record->created_at->diffInDays(now()) >= 3 ? 'op-row-warn' : null)
            ->striped()
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('All clear')
            ->emptyStateDescription('No orders awaiting confirmation in the last 7 days.');
    }
}
