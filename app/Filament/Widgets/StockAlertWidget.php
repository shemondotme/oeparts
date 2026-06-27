<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StockAlertWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Products currently out of stock';
    }

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -22;

    protected static ?string $heading = 'Out of Stock';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getExportHeaders(): array
    {
        return ['OEM Number', 'Manufacturer', 'Price'];
    }

    protected function getExportRows(): iterable
    {
        return Product::query()
            ->where('is_in_stock', false)
            ->where('is_active', true)
            ->with('manufacturer')
            ->latest()
            ->get()
            ->map(fn (Product $p) => [
                $p->oem_number,
                $p->manufacturer
                    ? (is_array($p->manufacturer->name)
                        ? ($p->manufacturer->name['en'] ?? reset($p->manufacturer->name))
                        : $p->manufacturer->name)
                    : '—',
                format_money($p->price),
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
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-check-badge',
                    'heading' => 'Stock levels healthy',
                    'description' => 'No items below reorder point.',
                    'ctaLabel' => '',
                    'ctaUrl' => '',
                ])
            )
            ->paginated(false)
            ->searchable(false);
    }
}
