<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LanguageResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Language;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class LanguageResource extends Resource
{
    protected static ?string $model = Language::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-language';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Language Details')
                                    ->icon('heroicon-o-language')
                                    ->description('Core language information and display settings.')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Language Code')
                                            ->placeholder('e.g. en, de, lt')
                                            ->required()
                                            ->maxLength(2)
                                            ->helperText('ISO 639-1 two-letter code (e.g. "en" for English).'),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Display Name')
                                            ->placeholder('e.g. English, German, Lithuanian')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Language name shown in admin and storefront language selectors.'),
                                        Forms\Components\TextInput::make('native_name')
                                            ->label('Native Name')
                                            ->placeholder('e.g. English, Deutsch, Lietuvių')
                                            ->maxLength(100)
                                            ->helperText('Language name in its own script (e.g. "Deutsch" for German).')
                                            // Same NOT-NULL-but-form-nullable
                                            // mismatch as flag_emoji above
                                            // (column is string('native_name', 50)
                                            // with no ->nullable(), migration
                                            // 2026_03_26_100003) — confirmed live.
                                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                                        Forms\Components\TextInput::make('locale')
                                            ->label('Locale Code')
                                            ->placeholder('e.g. en_US, de_DE, lt_LT')
                                            ->maxLength(10)
                                            ->helperText('Full locale code including country (e.g. "en_US" for US English).')
                                            // Same NOT-NULL-but-form-nullable
                                            // mismatch as native_name/flag_emoji
                                            // above (column is string('locale', 10)
                                            // with no ->nullable(), migration
                                            // 2026_03_26_100003) — confirmed live.
                                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                                        Forms\Components\TextInput::make('flag_emoji')
                                            ->label('Flag Emoji')
                                            ->placeholder('e.g. 🇬🇧, 🇩🇪, 🇱🇹')
                                            ->maxLength(10)
                                            ->helperText('Country flag emoji displayed next to the language name.')
                                            // The column is NOT NULL (migration
                                            // 2026_03_26_100003, string('flag_emoji', 10)
                                            // with no ->nullable()), but this field is
                                            // legitimately optional (a decorative
                                            // icon) — the form's own ->nullable() let
                                            // an admin submit with none and hit a raw
                                            // SQLSTATE NOT NULL constraint failure
                                            // instead of a friendly validation message,
                                            // confirmed live. Same fix pattern as
                                            // CarrierResource.tracking_url.
                                            ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                                    ])->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Configuration')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Language priority, visibility, and display ordering.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_default')
                                            ->label('Default Language')
                                            ->helperText('The fallback language when a translation is missing.'),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Language Active')
                                            ->helperText('Inactive languages are hidden from the storefront language selector.')
                                            ->default(true),
                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Display Order')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->helperText('Lower numbers appear first in language lists.'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('flag_emoji')
                    ->label('Flag')
                    ->size('lg')
                    ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('code')
                ->label('Code')
                ->badge()
                ->color('gray')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('name')
                ->label('Name')
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('native_name')
                    ->label('Native Name')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Language Status')
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Language')
                    ->placeholder('All')
                    ->trueLabel('Default Only')
                    ->falseLabel('Non-Default'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        // 'en' is the code-wide trans_field() fallback and the
                        // default language drives the storefront — protected.
                        // Explicit closure because Gate::before bypasses the
                        // policy for super_admins.
                        ->hidden(fn ($record): bool => \App\Policies\LanguagePolicy::isProtected($record)),
                ]),
            ])
        ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Languages', [
                    'code' => 'Code',
                    'name' => 'Name',
                    'native_name' => 'Native Name',
                    'is_default' => 'Default',
                    'is_active' => 'Active',
                    'sort_order' => 'Sort Order',
                ]),
                // No bulk delete: a handful of rows, two of them load-bearing
                // ('en' + the default) — deletes go through the guarded action.
            ]),
        ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-language')
            ->emptyStateHeading('No languages configured yet')
            ->emptyStateDescription('Add languages to enable multilingual content and storefront language switching.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Add Language')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLanguages::route('/'),
            'create' => Pages\CreateLanguage::route('/create'),
            'view'   => Pages\ViewLanguage::route('/{record}'),
            'edit'   => Pages\EditLanguage::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code'];
    }
}

