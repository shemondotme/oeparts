<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationResource\Pages;
use App\Models\LanguageString;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TranslationResource extends Resource
{
    protected static ?string $model = LanguageString::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-language';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Translation')
                    ->schema([
                        Forms\Components\Select::make('lang_code')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'de' => 'German',
                                'lt' => 'Lithuanian',
                                'fr' => 'French',
                                'es' => 'Spanish',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('group')
                            ->label('Group')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->maxLength(200),
                        Forms\Components\Textarea::make('value')
                            ->label('Value')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lang_code')
                    ->label('Lang')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('value')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lang_code')
                    ->label('Language')
                    ->options([
                        'en' => 'English',
                        'de' => 'German',
                        'lt' => 'Lithuanian',
                        'fr' => 'French',
                        'es' => 'Spanish',
                    ]),
                Tables\Filters\SelectFilter::make('group')
                    ->options(fn () => LanguageString::distinct()->pluck('group', 'group')->toArray()),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTranslations::route('/'),
            'create' => Pages\CreateTranslation::route('/create'),
            'edit' => Pages\EditTranslation::route('/{record}/edit'),
        ];
    }
}
