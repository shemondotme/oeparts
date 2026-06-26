<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NewProductsAdded extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\HasDashboardPeriod;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'New Products Added';

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -29;

    public function getDescription(): ?string
    {
        return 'Recently added products';
    }

    protected function getExportHeaders(): array
    {
        return ['OEM Number', 'Product', 'Manufacturer', 'Added'];
    }

    protected function getExportRows(): iterable
    {
        return Product::query()
            ->where('created_at', '>=', $this->periodStart())
            ->with(['manufacturer'])
            ->latest()
            ->get()
            ->map(function (Product $product): array {
                $mfr = $product->manufacturer;
                $mfrName = $mfr
                    ? ($mfr->name['en'] ?? (is_array($mfr->name) ? reset($mfr->name) : $mfr->name) ?? '—')
                    : '—';
                return [
                    $product->oem_number,
                    is_array($product->name) ? ($product->name['en'] ?? reset($product->name)) : ($product->name ?? '—'),
                    $mfrName,
                    $product->created_at?->format('d M Y H:i') ?? '—',
                ];
            });
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('created_at', '>=', $this->periodStart())
                    ->with(['manufacturer'])
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('oem_number')
                    ->label('SKU')
                    ->extraAttributes(['class' => 'oem-number'])
                    ->size('sm'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->limit(30)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (Product $record): string => $record->manufacturer ? ($record->manufacturer->name['en'] ?? (is_array($record->manufacturer->name) ? reset($record->manufacturer->name) : $record->manufacturer->name) ?? '—') : '—')
                    ->limit(15)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (Product $record): string => \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-cube-transparent')
            ->emptyStateHeading('No new products')
            ->emptyStateDescription('No products have been added recently. Add a product to see it here.')
            ->emptyStateActions([
                Tables\Actions\Action::make('add_product')
                    ->label('Add Product')
                    ->url(\App\Filament\Resources\ProductResource::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->color('primary'),
            ]);
    }
}
