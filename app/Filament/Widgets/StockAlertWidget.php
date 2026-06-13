<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlertWidget extends TableWidget
{
    public function getDescription(): ?string
    {
        return 'Products currently out of stock';
    }

    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -22;

    protected static ?string $heading = 'Out of Stock';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    public function table(Table $table): Table
    {
        try {
            $query = Product::query()
                ->where('is_in_stock', false)
                ->where('is_active', true)
                ->with('manufacturer')
                ->latest()
                ->limit(5);
        } catch (\Exception $e) {
            report($e);
            $query = Product::query()->whereRaw('1 = 0');
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('oem_number')
                    ->label('OEM')
                    ->fontMono()
                    ->limit(15)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Mfr')
                    ->getStateUsing(fn (Product $record): string => $record->manufacturer ? $record->manufacturer->name['en'] ?? (is_array($record->manufacturer->name) ? reset($record->manufacturer->name) : $record->manufacturer->name) ?? '—' : '—')
                    ->limit(15)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn (Product $record): string => format_money($record->price))
                    ->fontMono()
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record]))
                    ->size('sm')
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
