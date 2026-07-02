<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlertWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Products currently out of stock';
    }

    protected function getTableHeading(): string
    {
        $count = Product::where('is_in_stock', false)->where('is_active', true)->count();

        return 'Out of Stock' . ($count > 0 ? " ({$count})" : '');
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -22;

    protected static ?string $heading = 'Out of Stock';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(ProductResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        try {
            $query = Product::query()
                ->where('is_in_stock', false)
                ->where('is_active', true)
                ->with('manufacturer')
                ->latest()
                ->limit(10);
        } catch (\Exception $e) {
            report($e);
            $query = Product::query()->whereRaw('1 = 0');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('oem_number')
                    ->label('OEM')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->limit(24)
                    ->tooltip(fn (Product $record): ?string => mb_strlen((string) $record->oem_number) > 24 ? $record->oem_number : null)
                    ->description(fn (Product $record): string => $record->manufacturer ? ($record->manufacturer->name['en'] ?? (is_array($record->manufacturer->name) ? reset($record->manufacturer->name) : $record->manufacturer->name) ?? '—') : '—'),
                TextColumn::make('stock_flag')
                    ->label('Status')
                    ->state('Out of stock')
                    ->badge()
                    ->icon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn (Product $record): string => format_money($record->price))
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description('unit price')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('reorder')
                    ->label('Reorder')
                    ->icon('heroicon-o-truck')
                    ->color('warning')
                    ->size(\Filament\Support\Enums\Size::Small)
                    ->iconButton()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\ViewAction::make()
                    ->url(fn (Product $record): string => ProductResource::getUrl('view', ['record' => $record]))
                    ->size(\Filament\Support\Enums\Size::Small)
                    ->iconButton()
                    ->icon('heroicon-m-eye'),
            ])
            ->striped()
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('Everything in stock')
            ->emptyStateDescription('No active products are out of stock.')
            ->paginated(false)
            ->searchable(false);
    }
}
