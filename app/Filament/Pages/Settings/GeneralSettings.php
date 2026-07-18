<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;

class GeneralSettings extends SettingsPage
{
    protected static ?string $title = 'General Settings';

    protected static string $settingsGroup = 'general';

    protected static ?int $navigationSort = 10;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Identity & Branding')
                    ->description('Upload branding assets and configure primary public identity details.')
                    ->schema([
                        FileUpload::make('logo_id')
                            ->label('Site Logo')
                            ->helperText('Used in structured data (Organization JSON-LD) shown to search engines. The storefront navbar itself uses a coded brand mark, not this upload.')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        FileUpload::make('favicon_id')
                            ->label('Favicon')
                            ->helperText('Overrides the browser tab icon site-wide when set. Leave empty to keep the coded Industrial Blueprint mark.')
                            ->disk('public')
                            ->directory('branding')
                            ->image()
                            ->maxSize(512)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('site_name')
                            ->label('Site Name')
                            ->maxLength(255)
                            ->required()
                            ->default('OeParts'),

                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://oeparts.test')
                            ->default(null),

                        Forms\Components\TextInput::make('site_email')
                            ->label('Public Contact Email')
                            ->helperText('Canonical email shown in site header/footer. Contact Settings page has a separate email field for customer support routing.')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('info@oeparts.lt')
                            ->default(null),

                        Forms\Components\TextInput::make('site_phone')
                            ->label('Public Contact Phone')
                            ->helperText('Canonical phone for public display. Contact Settings page has a separate phone for support routing.')
                            ->tel()
                            ->minLength(5)
                            ->maxLength(30)
                            ->placeholder('+370 600 00000')
                            ->default(null),

                        Forms\Components\Placeholder::make('registered_address_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new \Illuminate\Support\HtmlString(
                                'Your registered company address (printed on invoices) is set on the <a href="'
                                . CompanySettings::getUrl()
                                . '" class="fi-link text-primary-600">Company Settings</a> page.'
                            )),
                    ])->columns(2),

                Section::make('Localization & Branding Defaults')
                    ->description('Set default currencies, languages, timezones, and display formatting rules.')
                    ->schema([
                        Forms\Components\TextInput::make('site_tagline')
                            ->label('Store Tagline')
                            ->helperText('Shown in the storefront footer.')
                            ->maxLength(255)
                            ->default('The central hub for genuine OEM auto parts in Europe.'),

                        Forms\Components\Select::make('default_locale')
                            ->label('Default Frontend Language')
                            ->options([
                                'en' => 'English (EN)',
                                'de' => 'Deutsch (DE)',
                                'lt' => 'Lietuvių (LT)',
                                'fr' => 'Français (FR)',
                                'es' => 'Español (ES)',
                            ])
                            ->required()
                            ->default('en'),

                        Forms\Components\Select::make('timezone')
                            ->label('System Timezone')
                            ->options(collect(\DateTimeZone::listIdentifiers(\DateTimeZone::EUROPE))
                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                ->toArray())
                            ->searchable()
                            ->required()
                            ->default('Europe/Vilnius'),

                        Forms\Components\Select::make('date_format')
                            ->label('System Date Format')
                            ->options([
                                'd/m/Y' => 'DD/MM/YYYY (e.g. 14/03/2025)',
                                'Y-m-d' => 'YYYY-MM-DD (e.g. 2025-03-14)',
                                'm/d/Y' => 'MM/DD/YYYY (e.g. 03/14/2025)',
                                'j F Y' => 'D Month YYYY (e.g. 14 March 2025)',
                            ])
                            ->required()
                            ->default('d/m/Y'),

                        Forms\Components\Select::make('currency')
                            ->label('Base Store Currency')
                            ->helperText('This is the single canonical currency setting used everywhere prices are displayed or charged. Store Settings shows it read-only for reference.')
                            ->options([
                                'EUR' => 'EUR (€)',
                                'USD' => 'USD ($)',
                                'GBP' => 'GBP (£)',
                                'CHF' => 'CHF (Fr)',
                                'PLN' => 'PLN (zł)',
                                'SEK' => 'SEK (kr)',
                            ])
                            ->required()
                            ->default('EUR'),

                        Forms\Components\TextInput::make('currency_symbol')
                            ->label('Currency Character')
                            ->helperText('Must match the Base Store Currency.')
                            ->maxLength(10)
                            ->placeholder('€')
                            ->required()
                            ->default('€'),
                    ])->columns(2),

                Section::make('Global Injection Scripts')
                    ->description('Inject trackers or customization script blocks directly into storefront markup.')
                    ->schema([
                        Forms\Components\Textarea::make('header_scripts')
                            ->label('Header Injection Block')
                            ->helperText('Injected inside <head> tags on all public pages')
                            ->rows(4)
                            ->columnSpanFull()
                            ->default(null),

                        Forms\Components\Textarea::make('footer_scripts')
                            ->label('Footer Injection Block')
                            ->helperText('Injected before the ending </body> tag on all public pages')
                            ->rows(4)
                            ->columnSpanFull()
                            ->default(null),
                    ]),
            ]);
    }
}
