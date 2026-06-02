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
                Section::make('VAT Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('default_vat_rate')
                            ->label('Default VAT Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->default(21),
                        Forms\Components\KeyValue::make('vat_rates')
                            ->label('VAT Rates by Country')
                            ->helperText('Country code as key (e.g. DE, FR, LT), rate as value')
                            ->keyLabel('Country Code')
                            ->valueLabel('VAT Rate')
                            ->addActionLabel('Add Country')
                            ->default([]),
                        Forms\Components\TextInput::make('company_vat_number')
                            ->label('Company VAT Number')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\Select::make('price_display')
                            ->label('Price Display')
                            ->options([
                                'inc_vat' => 'Including VAT',
                                'ex_vat' => 'Excluding VAT',
                            ])
                            ->default('inc_vat'),
                    ])->columns(2),
            ]);
    }
}
