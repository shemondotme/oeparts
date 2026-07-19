<?php

namespace App\Filament\Pages\Catalog;

use App\Enums\BulkUpdateAction;
use App\Models\BulkUpdateLog;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class BulkUpdateLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Bulk Update Log';

    protected string $view = 'filament.pages.catalog.bulk-update-log';

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 51;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bulk Update Log';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BulkUpdateLog::query()
                    ->with(['admin', 'targetManufacturer'])
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('admin.name')
                    ->label('By')
                    ->searchable()
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'price_increase' => 'Price ↑',
                        'price_decrease' => 'Price ↓',
                        'stock_in' => 'Stock In',
                        'stock_out' => 'Stock Out',
                        'import' => 'Import',
                        default => ucfirst($state),
                    })
                    ->color(fn ($state): string => match ($state) {
                        'price_increase' => 'success',
                        'price_decrease' => 'danger',
                        'stock_in' => 'info',
                        'stock_out' => 'warning',
                        'import' => 'primary',
                        default => 'gray',
                    })
                    ->size('sm'),

                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->formatStateUsing(fn ($state): string => class_basename($state ?? ''))
                    ->searchable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('targetManufacturer.name')
                    ->label('Manufacturer')
                    ->searchable()
                    ->default('—')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('affected_rows_count')
                    ->label('Affected')
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontMono()
                    ->size('sm'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll('60s')
            ->filters([
                Tables\Filters\SelectFilter::make('action_type')
                    ->label('Action Type')
                    ->options([
                        'price_increase' => 'Price Increase',
                        'price_decrease' => 'Price Decrease',
                        'stock_in' => 'Stock In',
                        'stock_out' => 'Stock Out',
                        'import' => 'Import',
                    ])
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
            ])
            ->actions([
                Tables\Actions\Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Bulk Update Details')
                    ->modalContent(function ($record) {
                        return view('filament.pages.catalog.bulk-update-detail', ['record' => $record]);
                    })
                    ->modalSubmitAction(false),
            ]);
    }
}
