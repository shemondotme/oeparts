<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AbandonedCartResource;
use App\Models\AbandonedCart;
use App\Services\CartRecoveryService;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AbandonedCartWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Carts left before checkout — recovery opportunity';
    }

    protected function getTableHeading(): string
    {
        $count = AbandonedCart::where('recovery_email_sent', false)->count();

        return 'Abandoned Carts' . ($count > 0 ? " ({$count})" : '');
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -21;

    protected static ?string $heading = 'Abandoned Carts';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(AbandonedCartResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AbandonedCart::query()
                    ->with('user')
                    ->latest('last_active_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('user.email')
                    ->label('Customer')
                    ->getStateUsing(fn (AbandonedCart $record): string => $record->guest_email ?? $record->user?->email ?? '—')
                    ->weight(FontWeight::Bold)
                    ->limit(32)
                    ->description(function (AbandonedCart $record): string {
                        $items = count($record->cart_snapshot['items'] ?? []);
                        $total = $record->cart_snapshot['total'] ?? null;

                        return $items . ' item' . ($items === 1 ? '' : 's')
                            . ($total !== null ? ' · ' . format_money($total) : '');
                    }),
                TextColumn::make('recovery_email_sent')
                    ->label('Recovery')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sent' : 'Not sent')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                TextColumn::make('last_active_at')
                    ->label('Last Active')
                    ->since()
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('send_recovery')
                    ->label(fn (AbandonedCart $record): string => $record->recovery_email_sent ? 'Send Again' : 'Send Recovery')
                    ->icon('heroicon-o-paper-airplane')
                    ->color(fn (AbandonedCart $record): string => $record->recovery_email_sent ? 'gray' : 'info')
                    ->size('sm')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalHeading(fn (AbandonedCart $record): string => $record->recovery_email_sent ? 'Send Recovery Email Again' : 'Send Recovery Email')
                    ->modalDescription(fn (AbandonedCart $record): string => $record->recovery_email_sent
                        ? 'A recovery email was already sent for this cart — sending another should be a deliberate choice.'
                        : 'Send a cart recovery email to the customer reminding them of the items left in their cart.')
                    ->action(function (AbandonedCart $record): void {
                        if (! app(CartRecoveryService::class)->send($record)) {
                            Notification::make()
                                ->title('No email address')
                                ->body('This abandoned cart has no associated customer or guest email.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Recovery email sent')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->url(fn (AbandonedCart $record): string => AbandonedCartResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->striped()
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('No abandoned carts')
            ->emptyStateDescription('Every cart made it to checkout.')
            ->paginated(false)
            ->searchable(false);
    }
}
