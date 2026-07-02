<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AbandonedCartResource;
use App\Models\Cart;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AbandonedCartWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Carts left before checkout';
    }

    protected function getTableHeading(): string
    {
        $count = Cart::where('updated_at', '<', now()->subHours((int) settings('dashboard.cart_abandoned_hours', 2)))
            ->whereHas('items')
            ->count();

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
                Cart::query()
                    ->withCount('items')
                    ->where('updated_at', '<', now()->subHours((int) settings('dashboard.cart_abandoned_hours', 2)))
                    ->whereHas('items')
                    ->latest('updated_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('user.email')
                    ->label('Customer')
                    ->weight(FontWeight::Bold)
                    ->placeholder('Guest checkout')
                    ->limit(32)
                    ->tooltip(fn (Cart $record): ?string => $record->user?->email && mb_strlen((string) $record->user->email) > 32 ? $record->user->email : null)
                    ->description(fn (Cart $record): string => $record->items_count . ' item' . ((int) $record->items_count === 1 ? '' : 's') . ' in cart'),
                TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->since()
                    ->color(function (Cart $record): string {
                        $hours = now()->diffInHours($record->updated_at);
                        if ($hours < 12) return 'success';
                        if ($hours < 24) return 'warning';
                        return 'danger';
                    })
                    ->weight(FontWeight::Medium)
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('send_reminder')
                    ->label('Send Reminder')
                    ->icon('heroicon-o-bell')
                    ->color('warning')
                    ->size('sm')
                    ->disabled(fn (Cart $record): bool => $record->user?->email === null)
                    ->tooltip(fn (Cart $record): ?string => $record->user?->email === null
                        ? 'No email on file — guest checkout'
                        : null)
                    ->action(function (Cart $record): void {
                        $record->touch('reminded_at');
                        \Filament\Notifications\Notification::make()
                            ->title('Reminder sent')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->url(fn (Cart $record): string => AbandonedCartResource::getUrl('view', ['record' => $record]))
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
