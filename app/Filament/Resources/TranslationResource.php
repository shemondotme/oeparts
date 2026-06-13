<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\LanguageString;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

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
                Section::make('Translation Details')
                    ->icon('heroicon-o-language')
                    ->description('Define the language, group, and key-value pair for this translation string.')
                    ->schema([
                        Forms\Components\Select::make('lang_code')
                            ->label('Target Language')
                            ->options(AdminUi::LOCALES)
                            ->native(false)
                            ->required()
                            ->helperText('The language this translation is for.'),
                        Forms\Components\TextInput::make('group')
                            ->label('Translation Group')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. general, validation, errors')
                            ->helperText('Group related translations together (e.g. "validation" for form error messages).'),
                        Forms\Components\TextInput::make('key')
                            ->label('Translation Key')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('e.g. welcome_message, email_required')
                            ->helperText('The unique identifier for this string. Used in code to reference the translation.'),
                        Forms\Components\Textarea::make('value')
                            ->label('Translated Value')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull()
                            ->placeholder('Enter the translated text for this key')
                            ->helperText('The actual text displayed to users in this language.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('lang_code')
                    ->label('Language')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                    ->color('gray')
                    ->sortable(),
            Tables\Columns\TextColumn::make('group')
                ->label('Group')
                ->badge()
                ->color('info')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('key')
                ->label('Key Name')
                ->weight(FontWeight::Medium)
                ->searchable()
                ->sortable()
                ->limit(40),
                Tables\Columns\TextColumn::make('value')
                    ->label('Translation Value')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lang_code')
                    ->label('Language')
                    ->options(AdminUi::LOCALES)
                    ->native(false)
                    ->helperText('Filter translations by target language.'),
                Tables\Filters\SelectFilter::make('group')
                    ->label('Translation Group')
                    ->options(fn () => LanguageString::distinct()->pluck('group', 'group')->toArray())
                    ->native(false)
                    ->helperText('Filter by translation group (e.g. validation, errors).'),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
            ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Translations', [
                    'lang_code' => 'Language',
                    'group' => 'Group',
                    'key' => 'Key',
                    'value' => 'Value',
                    'updated_at' => 'Updated',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateIcon('heroicon-o-language')
            ->emptyStateHeading('No translation strings created yet')
            ->emptyStateDescription('Create translation key-value pairs for each supported language to enable multilingual storefront content.');
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
            'view' => Pages\ViewTranslation::route('/{record}'),
            'edit' => Pages\EditTranslation::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['key', 'value'];
    }
}

