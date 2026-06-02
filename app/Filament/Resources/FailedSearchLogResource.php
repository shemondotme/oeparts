<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedSearchLogResource\Pages;
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
        return 'heroicon-o-magnifying-glass';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
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
                    ->schema([
                        Forms\Components\TextInput::make('search_query')
                            ->label('Query')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('normalized_query')
                            ->label('Normalized')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('lang')
                            ->label('Language')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('user.name')
                            ->label('User')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Toggle::make('inquiry_submitted')
                            ->label('Inquiry Submitted')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Date')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('search_query')
                    ->label('Query')
                    ->searchable()
                    ->copyable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('normalized_query')
                    ->label('Normalized')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Lang')
                    ->badge()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('inquiry_submitted')
                    ->label('Inquiry?')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lang')
                    ->options([
                        'en' => 'English',
                        'de' => 'German',
                        'lt' => 'Lithuanian',
                        'fr' => 'French',
                        'es' => 'Spanish',
                    ]),
                Tables\Filters\TernaryFilter::make('inquiry_submitted')
                    ->label('Inquiry Submitted'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
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

}
