<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Resources\LanguageResource;
use App\Filament\Resources\TranslationResource;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Thin by design: languages and translation strings are both managed as
 * data via their own Resources (add/remove locales, per-locale key/value
 * editing) — there is nothing left for a settings *form* to own, so this
 * page is entirely cross-link Placeholders into those two Resources. It
 * exists purely to make Localization findable as its own category rather
 * than two unrelated Resources buried elsewhere in the admin nav; see
 * SettingsRegistry's "cross-link, don't duplicate the editing UI" precedent
 * (also used for Shipping/Tax/Backup/Updates).
 *
 * $settingsGroup = 'localization' is intentionally permanently empty — no
 * SettingsSeeder rows exist or ever will for it. SettingsFactoryDefaultsTest
 * already has a documented skip-path for a page with zero seeded keys (see
 * its "about/database: display-only" comment), the same path this page
 * takes.
 */
class LocalizationSettings extends SettingsPage
{
    protected static ?string $title = 'Localization';

    protected static string $settingsGroup = 'localization';

    protected static ?int $navigationSort = 35;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-language';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Languages')
                    ->description('Which locales the storefront and admin panel support.')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('language_resource_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Add, remove, and reorder supported locales on the <a href="'
                                . LanguageResource::getUrl()
                                . '" class="fi-link text-primary-600">Languages</a> page.'
                            )),
                    ]),

                Section::make('Translations')
                    ->description('Per-locale text strings used across the storefront and emails.')
                    ->schema([
                        \Filament\Forms\Components\Placeholder::make('translation_resource_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Edit translation strings on the <a href="'
                                . TranslationResource::getUrl()
                                . '" class="fi-link text-primary-600">Translations</a> page.'
                            )),
                    ]),
            ]);
    }

    /**
     * Every field on this page is a bare Placeholder (no statePath, no
     * rules) — the base SettingsPage::save()'s unconditional $this->validate()
     * throws Livewire's MissingRulesException when a form contributes zero
     * rules across its entire schema. There is genuinely nothing to save
     * here, so skip straight to that outcome instead of validating.
     */
    public function save(): void
    {
        Notification::make()
            ->title('Nothing to save here')
            ->body('This page only links out to Languages and Translations — edit those directly.')
            ->info()
            ->send();
    }
}
