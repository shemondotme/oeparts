<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedSearchLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\FailedSearchLog;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FailedSearchLogResource extends Resource
{
    protected static ?string $model = FailedSearchLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-x-circle';
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('failed_searches_today', fn () => static::getModel()::where('created_at', '>=', now()->startOfDay())->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Searches with no results today';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 90;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'search_query';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Search Details')
                    ->description('Read-only details of a search query that returned no results.')
                    ->schema([
                        Forms\Components\TextInput::make('search_query')
                            ->label('Search Query')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('normalized_query')
                            ->label('Normalized Query')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('lang')
                            ->label('Language')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user.name')
                            ->label('Searched By')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Toggle::make('inquiry_submitted')
                            ->label('Inquiry Submitted')
                            ->helperText('Whether the user submitted a part inquiry after the failed search.')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Searched At')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('search_query')
                    ->label('Search Query')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Query copied')
                    ->limit(30)
                    ->fontMono(),
                Tables\Columns\TextColumn::make('normalized_query')
                    ->label('Normalized Query')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Language')
                    ->badge()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('inquiry_submitted')
                    ->label('Inquiry?')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontMono()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Searched At')
                    ->dateTime()
                    ->sortable(),
            ])
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
                    ->helperText('Filter failed searches by language.'),
                Tables\Filters\TernaryFilter::make('inquiry_submitted')
                    ->label('Inquiry Submitted')
                    ->helperText('Show searches that did or did not result in a part inquiry.'),
            ])
            ->actions([
                ...AdminUi::recordActionsReadOnly(),
            ])
            ->emptyStateIcon('heroicon-o-x-circle')
            ->emptyStateHeading('No failed searches logged')
            ->emptyStateDescription('Failed searches help identify gaps in your catalog. Consider adding parts that customers frequently search for.')
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Failed Searches', [
                        'search_query' => 'Query',
                        'normalized_query' => 'Normalized',
                        'lang' => 'Language',
                        'inquiry_submitted' => 'Inquiry Submitted',
                        'ip_address' => 'IP Address',
                        'created_at' => 'Date',
                    ]),
                    // No bulk delete: read-only demand-signal log; retention
                    // is logs:clean's job.
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFailedSearchLogs::route('/'),
            'view'  => Pages\ViewFailedSearchLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['search_query'];
    }
}
