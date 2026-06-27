<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentOrdersList extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Most recent orders placed';
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -20;

    protected static ?string $heading = 'Recent Orders';

    protected function getExportHeaders(): array
    {
        return ['Order #', 'Customer', 'Total', 'Status', 'Date'];
    }

    protected function getExportRows(): iterable
    {
        return Order::query()
            ->with(['user'])
            ->where('created_at', '>=', $this->periodStart())
            ->latest()
            ->get()
            ->map(fn (Order $o) => [
                $o->order_number,
                $o->shipping_name ?? $o->user?->name ?? $o->guest_email ?? '—',
                format_money($o->grand_total),
                $o->status instanceof \App\Enums\OrderStatus ? $o->status->value : ($o->status ?? '—'),
                $o->created_at?->format('d M Y H:i') ?? '—',
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
                ->url(\App\Filament\Resources\OrderResource::getUrl('index')),
        ];
    }

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->with(['user'])
                    ->where('created_at', '>=', $this->periodStart())
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Order number copied')
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->getStateUsing(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                    ->limit(20),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label('Total')
                    ->getStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state instanceof \App\Enums\OrderStatus ? $state->value : $state) {
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Order $record): string => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-shopping-bag',
                    'heading' => 'No recent orders',
                    'description' => 'Create your first order to see metrics here.',
                    'ctaLabel' => 'Create Order',
                    'ctaUrl' => \App\Filament\Resources\OrderResource::getUrl('create'),
                ])
            )
            ->emptyStateDescription('')
            ->searchable(false)
            ->paginated(false);
    }
}
