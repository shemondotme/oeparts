<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RefundRequestResource;
use App\Models\RefundRequest;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
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
        return 'Refund requests awaiting review';
    }

    protected function getTableHeading(): string
    {
        $d = $this->cachedWidgetData(fn (): array => ['count' => RefundRequest::pending()->count()]);

        return 'Refunds Pending' . ($d['count'] > 0 ? " ({$d['count']})" : '');
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(RefundRequestResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RefundRequest::pending()
                    ->with(['order', 'user'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description(fn (RefundRequest $record): string => $record->user?->name ?? $record->order?->shipping_name ?? '—'),
                TextColumn::make('created_at')
                    ->label('Days Open')
                    ->badge()
                    ->icon('heroicon-m-clock')
                    ->getStateUsing(fn (RefundRequest $record): string => (int) $record->created_at->diffInDays(now()) . ' days')
                    ->color(function (RefundRequest $record): string {
                        $days = (int) $record->created_at->diffInDays(now());
                        if ($days < 14) {
                            return 'success';
                        }
                        if ($days < 27) {
                            return 'warning';
                        }
                        return 'danger';
                    }),
                TextColumn::make('amount_requested')
                    ->label('Requested')
                    ->formatStateUsing(fn (RefundRequest $record): string => format_money($record->amount_requested))
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->color('danger')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('Refund exposure')
                            ->formatStateUsing(fn ($state): string => format_money($state))
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-m-eye')
                    ->color('warning')
                    ->url(fn (RefundRequest $record): string => RefundRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->recordClasses(function (RefundRequest $record): ?string {
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
