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
                            ->helperText('Flat rate applied to every order regardless of customer country. Destination-based (per-country) VAT is a compliance decision — confirm with your accountant before this store would need it.')
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
