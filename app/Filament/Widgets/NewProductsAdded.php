<?php

namespace App\Filament\Widgets;

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

class NewProductsAdded extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\HasDashboardPeriod;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'New Products Added';

    protected ?string $pollingInterval = '120s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -29;

    public function getDescription(): ?string
    {
        return 'Recently added products';
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\ProductResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('created_at', '>=', $this->periodStart())
                    ->with(['manufacturer'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->weight(FontWeight::Bold)
                    ->limit(36)
                    ->tooltip(fn (Product $record): ?string => mb_strlen(trans_field($record->name)) > 36 ? trans_field($record->name) : null)
                    ->description(fn (Product $record): string => $record->oem_number ?? '—'),
                TextColumn::make('manufacturer.name')
                    ->label('Manufacturer')
                    ->getStateUsing(fn (Product $record): string => $record->manufacturer ? ($record->manufacturer->name['en'] ?? (is_array($record->manufacturer->name) ? reset($record->manufacturer->name) : $record->manufacturer->name) ?? '—') : '—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->color('gray')
                    ->description('added')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (Product $record): string => \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $record])),
            ])
            ->striped()
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
