<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class TaxSettings extends SettingsPage
{
    protected static ?string $title = 'Tax Settings';

    protected static string $settingsGroup = 'tax';

    protected static ?int $navigationSort = 11;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('EU VAT Configurations')
                    ->description('Specify standard VAT percents and localized country rates. Integrates automatically with VAT verification services.')
                    ->schema([
                        Forms\Components\Placeholder::make('company_vat_number_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new \Illuminate\Support\HtmlString(
                                'Your own company VAT registration number (printed on generated customer invoices) is set on the <a href="'
                                . GeneralBrandSettings::getUrl()
                                . '" class="fi-link text-primary-600">General & Brand</a> page\'s Company & Legal tab, alongside your registered address and contact details.'
                            )),

                        Forms\Components\TextInput::make('default_vat_rate')
                            ->label('Default Store VAT Percent (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->helperText('Fallback rate — used for every order when Country-Based VAT (below) is off, and for any country with no active rate configured.')
                            ->default(21),

                        Forms\Components\Select::make('price_display')
                            ->label('Storefront Catalog Price Display')
                            ->options([
                                'inc_vat' => 'Including VAT (B2C Standard)',
                                'ex_vat' => 'Excluding VAT (B2B Standard)',
                            ])
                            ->required()
                            ->default('inc_vat'),
                    ])->columns(2),

                Section::make('Country-Based VAT')
                    ->description('Charge each order VAT at the rate of the customer\'s shipping country instead of one flat rate for everyone.')
                    ->schema([
                        Forms\Components\Toggle::make('country_based_vat_enabled')
                            ->label('Enable Country-Based VAT')
                            ->helperText('When off (default), every order uses the flat Default Store VAT Percent above regardless of country.')
                            ->live()
                            ->default(false),

                        Forms\Components\Placeholder::make('tax_rates_note')
                            ->label('')
                            ->columnSpanFull()
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => (bool) $get('country_based_vat_enabled'))
                            ->content(new \Illuminate\Support\HtmlString(
                                'Manage per-country rates on the <a href="'
                                . \App\Filament\Resources\TaxRateResource::getUrl('index')
                                . '" class="fi-link text-primary-600">Tax Rates</a> page. Seeded starting rates are provided — '
                                . '<strong>verify every rate before relying on it</strong>, standard VAT rates change and the seeded '
                                . 'values may be out of date. A country with no active rate configured falls back to the flat rate above.'
                            )),
                    ])->columns(1),

                Section::make('VIES VAT Validation')
                    ->description('Control the EU VIES integration for real-time VAT number verification.')
                    ->schema([
                        Forms\Components\Toggle::make('vat_validation_enabled')
                            ->label('Enable VIES VAT Validation')
                            ->helperText('Verify EU VAT numbers via the VIES SOAP API (used by the /api/validate-vat endpoint)')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
