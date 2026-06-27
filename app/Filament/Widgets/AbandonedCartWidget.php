<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\AbandonedCartResource;
use App\Models\Cart;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AbandonedCartWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Carts left before checkout';
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -21;

    protected static ?string $heading = 'Abandoned Carts';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getExportHeaders(): array
    {
        return ['User', 'Items', 'Last Active'];
    }

    protected function getExportRows(): iterable
    {
        return Cart::query()
            ->withCount('items')
            ->with('user')
            ->where('updated_at', '<', now()->subHours((int) settings('dashboard.cart_abandoned_hours', 2)))
            ->whereHas('items')
            ->latest('updated_at')
            ->get()
            ->map(fn (Cart $cart) => [
                $cart->user?->email ?? 'Guest',
                $cart->items_count,
                $cart->updated_at?->format('d M Y H:i') ?? '—',
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
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.email')
                    ->label('User')
                    ->limit(25)
                    ->placeholder('Guest')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->fontMono()
                    ->size('sm'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Active')
                    ->since()
                    ->color(function (Cart $record): string {
                        $hours = now()->diffInHours($record->updated_at);
                        if ($hours < 12) return 'success';
                        if ($hours < 24) return 'warning';
                        return 'danger';
                    })
                    ->size('sm'),
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
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-check-circle',
                    'heading' => 'No abandoned carts',
                    'description' => 'Great! Your checkout flow is working.',
                    'ctaLabel' => '',
                    'ctaUrl' => '',
                ])
            )
            ->paginated(false)
            ->searchable(false);
    }
}
