<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\LanguageString;
use App\Models\Setting;
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

    /**
     * Maps a LanguageString.group value (matches the lang/{locale}/{group}.php
     * basename Laravel's translator loads it under — confirmed directly via
     * DatabaseTranslationLoader::load()'s $group param) to the ui.* prefix
     * UiCopyInstaller seeded that same source file's strings under. Only
     * these 6 groups can ever be shadowed — UiCopyInstaller never seeded
     * anything else. checkout/account/footer were added alongside
     * SiteCopyLibrary's expansion to those prefixes (see
     * memory/project_ui_copy_text_override_gap.md) — no language_strings
     * rows exist for these groups yet, but TranslationResource's group
     * field is free text, so an admin could create one at any time.
     *
     * @var array<string, string>
     */
    public const SHADOWING_PREFIX_MAP = [
        'search' => 'search_',
        'cart' => 'cart_',
        'navbar' => 'nav_',
        'checkout' => 'checkout_',
        'account' => 'account_',
        'footer' => 'footer_',
    ];

    /**
     * True iff editing this row would have NO visible effect on the
     * storefront — ui_copy() checks settings_trans('ui.'.$key) BEFORE ever
     * falling back to __() (which is what reads language_strings), so a
     * non-empty ui.* override for the same locale silently wins every
     * time. See memory/project_ui_copy_text_override_gap.md's "shadow
     * trap" finding — this is the fix.
     */
    public static function isShadowed(LanguageString $record): bool
    {
        $prefix = self::SHADOWING_PREFIX_MAP[$record->group] ?? null;

        if ($prefix === null) {
            return false;
        }

        $uiValue = Setting::where('group', 'ui')->where('key', $prefix . $record->key)->value('value');

        if (blank($uiValue)) {
            return false;
        }

        $decoded = json_decode((string) $uiValue, true);

        if (! is_array($decoded)) {
            return false;
        }

        return filled($decoded[$record->lang_code] ?? null);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-language';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
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
                            ->label(__('admin.target_language'))
                            ->options(AdminUi::LOCALES)
                            ->native(false)
                            ->required()
                            ->helperText('The language this translation is for.'),
                        Forms\Components\TextInput::make('group')
                            ->label(__('admin.translation_group'))
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. general, validation, errors')
                            ->helperText('Group related translations together (e.g. "validation" for form error messages).'),
                        Forms\Components\TextInput::make('key')
                            ->label(__('admin.translation_key'))
                            ->required()
                            ->maxLength(200)
                            ->placeholder('e.g. welcome_message, email_required')
                            ->helperText('The unique identifier for this string. Used in code to reference the translation.'),
                        Forms\Components\Textarea::make('value')
                            ->label(__('admin.translated_value'))
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
                    ->label(__('admin.language'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminUi::LOCALES[$state] ?? strtoupper($state))
                    ->color('gray')
                    ->sortable(),
            Tables\Columns\TextColumn::make('group')
                ->label(__('admin.group'))
                ->badge()
                ->color('info')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('key')
                ->label(__('admin.key_name'))
                ->weight(FontWeight::Medium)
                ->searchable()
                ->sortable()
                ->limit(40),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('admin.translation_value'))
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\IconColumn::make('shadowed')
                    ->label('')
                    ->state(fn (LanguageString $record): bool => self::isShadowed($record))
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('warning')
                    ->tooltip(fn (bool $state): ?string => $state
                        ? 'A Site Copy Library text override exists for this key/locale and takes precedence — editing this row has no visible effect on the storefront until that override is cleared.'
                        : null),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.updated'))
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lang_code')
                    ->label(__('admin.language'))
                    ->options(AdminUi::LOCALES)
                    ->native(false)
                    ->helperText('Filter translations by target language.'),
                Tables\Filters\SelectFilter::make('group')
                    ->label(__('admin.translation_group'))
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

