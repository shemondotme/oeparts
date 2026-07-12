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
                                . CompanySettings::getUrl()
                                . '" class="fi-link text-primary-600">Company Settings</a> page, alongside your registered address and contact details.'
                            )),

                        Forms\Components\TextInput::make('default_vat_rate')
                            ->label('Default Store VAT Percent (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->helperText('Standard rate used when customer country is not matched in custom rates list')
                            ->default(21),

                        Forms\Components\Select::make('price_display')
                            ->label('Storefront Catalog Price Display')
                            ->options([
                                'inc_vat' => 'Including VAT (B2C Standard)',
                                'ex_vat' => 'Excluding VAT (B2B Standard)',
                            ])
                            ->required()
                            ->default('inc_vat'),

                        Forms\Components\KeyValue::make('vat_rates')
                            ->label('Specific Country VAT Rates Override')
                            ->helperText('Add specific country codes (2-letter ISO, e.g. DE, FR, PL) as key, rate percent as value')
                            ->keyLabel('Country Code (ISO)')
                            ->valueLabel('VAT Percent (%)')
                            ->addActionLabel('Add Country Rate')
                            ->columnSpanFull()
                            ->default([]),
                    ])->columns(2),

                Section::make('VIES VAT Validation')
                    ->description('Control the EU VIES integration for real-time VAT number verification.')
                    ->schema([
                        Forms\Components\Toggle::make('vat_validation_enabled')
                            ->label('Enable VIES VAT Validation')
                            ->helperText('Verify customer EU VAT numbers via the VIES SOAP API at checkout')
                            ->default(true),

                        Forms\Components\Toggle::make('b2b_exempt_on_valid_vat')
                            ->label('Exempt B2B Orders with Valid VAT')
                            ->helperText('When a valid EU VAT number is provided, exclude VAT from the order total')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
