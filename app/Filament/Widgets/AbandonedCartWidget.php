<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AbandonedCartResource;
use App\Models\Cart;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AbandonedCartWidget extends TableWidget
{
    public function getDescription(): ?string
    {
        return 'Carts left before checkout';
    }

    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -21;

    protected static ?string $heading = 'Abandoned Carts';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

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
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Cart $record): string => AbandonedCartResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
