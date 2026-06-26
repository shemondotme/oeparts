<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SearchLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\SearchLog;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class SearchLogResource extends Resource
{
    protected static ?string $model = SearchLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-magnifying-glass';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) SearchLog::whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?int $navigationSort = 80;

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with(['manufacturer', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('search_query')
                    ->label('Query')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('result_count')
                    ->label('Results')
                    ->numeric()
                    ->sortable()
                    ->fontMono()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Manufacturer'),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Language')
                    ->badge()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontMono()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('lang')
                    ->label('Language')
                    ->options([
                        'en' => 'English',
                        'de' => 'German',
                        'lt' => 'Lithuanian',
                        'fr' => 'French',
                        'es' => 'Spanish',
                    ])
                    ->native(false)
                    ->helperText('Filter searches by the language used.'),
            ])
            ->actions([
                ...AdminUi::recordActionsReadOnly(),
            ])
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->emptyStateHeading('No searches logged yet')
            ->emptyStateDescription('Customer search queries will appear here, helping you understand what parts users are looking for.')
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Search Logs', [
                        'search_query' => 'Query',
                        'result_count' => 'Results',
                        'manufacturer.name' => 'Manufacturer',
                        'lang' => 'Language',
                        'user.name' => 'User',
                        'ip_address' => 'IP Address',
                        'created_at' => 'Date',
                    ]),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSearchLogs::route('/'),
            'view' => Pages\ViewSearchLog::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['search_query'];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
