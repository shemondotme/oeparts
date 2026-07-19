<?php

namespace App\Filament\Pages\Catalog;

use App\Enums\InventoryChangeType;
use App\Models\InventoryLog;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class InventoryLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Inventory Log';

    protected string $view = 'filament.pages.catalog.inventory-log';

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventory Log';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                InventoryLog::query()
                    ->with(['product', 'admin'])
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('product.oem_number')
                    ->label('OEM Number')
                    ->searchable()
                    ->sortable()
                    ->extraAttributes(['class' => 'oem-number'])
                    ->size('sm'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->limit(40)
                    ->size('sm'),

                Tables\Columns\TextColumn::make('change_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (InventoryChangeType $state): string => match ($state) {
                        InventoryChangeType::CsvImport => 'CSV Import',
                        InventoryChangeType::Manual => 'Manual',
                        InventoryChangeType::BulkUpdate => 'Bulk Update',
                        InventoryChangeType::System => 'System',
                    })
                    ->color(fn (InventoryChangeType $state): string => match ($state) {
                        InventoryChangeType::CsvImport => 'info',
                        InventoryChangeType::Manual => 'warning',
                        InventoryChangeType::BulkUpdate => 'success',
                        InventoryChangeType::System => 'gray',
                    })
                    ->size('sm'),

                Tables\Columns\TextColumn::make('old_status')
                    ->label('Status Change')
                    ->formatStateUsing(fn ($state, $record): string => $state ? 'In Stock' : 'Out of Stock')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'danger')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('new_status')
                    ->label('→')
                    ->formatStateUsing(fn ($state): string => $state ? 'In Stock' : 'Out of Stock')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'success' : 'danger')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('admin.name')
                    ->label('By')
                    ->searchable()
                    ->sortable()
                    ->default('System')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(50)
                    ->wrap()
                    ->size('sm'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll('60s')
            ->filters([
                Tables\Filters\SelectFilter::make('change_type')
                    ->label('Change Type')
                    ->options(InventoryChangeType::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('admin_id')
                    ->label('Admin')
                    ->options(fn () => \App\Models\Admin::pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Select::make('created_at')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'quarter' => 'This Quarter',
                            ])
                            ->placeholder('All Time'),
                    ])
                    ->query(function ($query, array $data): void {
                        if (empty($data['created_at'])) {
                            return;
                        }

                        $query->whereDate('created_at', match ($data['created_at']) {
                            'today' => now()->toDateString(),
                            'yesterday' => now()->subDay()->toDateString(),
                            'week' => now()->startOfWeek()->toDateString(),
                            'month' => now()->startOfMonth()->toDateString(),
                            'quarter' => now()->startOfQuarter()->toDateString(),
                            default => now()->toDateString(),
                        });
                    }),
            ]);
    }
}
