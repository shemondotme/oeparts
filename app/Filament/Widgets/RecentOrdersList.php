<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
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

class RecentOrdersList extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Most recent orders placed';
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -20;

    protected static ?string $heading = 'Recent Orders';

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

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['user'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                // Fixed, aligned columns (not Split) so status/amount line up
                // across rows; ->description() keeps the 2-line card richness.
                TextColumn::make('order_number')
                    ->label('Order')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Order number copied'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->icon(fn (mixed $state): string => match ($state instanceof OrderStatus ? $state->value : $state) {
                        'pending' => 'heroicon-m-clock',
                        'paid' => 'heroicon-m-banknotes',
                        'processing' => 'heroicon-m-cog-6-tooth',
                        'shipped' => 'heroicon-m-truck',
                        'delivered' => 'heroicon-m-check-circle',
                        'cancelled' => 'heroicon-m-x-circle',
                        'refund_requested' => 'heroicon-m-receipt-refund',
                        'refunded' => 'heroicon-m-arrow-uturn-left',
                        default => 'heroicon-m-ellipsis-horizontal',
                    })
                    ->color(fn (mixed $state): string => match ($state instanceof OrderStatus ? $state->value : $state) {
                        'pending' => 'warning',
                        'paid' => 'info',
                        'processing' => 'primary',
                        'shipped' => 'success',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refund_requested' => 'warning',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->formatStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (Order $record): string => $record->created_at?->diffForHumans() ?? '—')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->striped()
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('New orders will appear here as they come in.')
            ->searchable(false)
            ->paginated(false);
    }
}
