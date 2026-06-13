<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class CompanySettings extends SettingsPage
{
    protected static ?string $title = 'Company Settings';

    protected static string $settingsGroup = 'company';

    protected static ?int $navigationSort = 5;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Information')
                    ->description('Company details used on invoices, emails, and legal pages.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Company Name')
                            ->maxLength(255)
                            ->required()
                            ->default('OeParts'),

                        Forms\Components\TextInput::make('vat_number')
                            ->label('VAT Number')
                            ->maxLength(50)
                            ->placeholder('LT123456789')
                            ->default(null),

                        Forms\Components\TextInput::make('registration_number')
                            ->label('Registration Number')
                            ->maxLength(50)
                            ->placeholder('123456789')
                            ->default(null),
                    ])->columns(2),

                Section::make('Contact Details')
                    ->description('Contact information for customer-facing communications.')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Company Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('info@oeparts.lt')
                            ->default(null),

                        Forms\Components\TextInput::make('phone')
                            ->label('Company Phone')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('+370 600 00000')
                            ->default(null),

                        Forms\Components\Textarea::make('address')
                            ->label('Registered Address')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder("Ulonų g. 5, Vilnius, Lithuania")
                            ->columnSpanFull()
                            ->default(null),
                    ])->columns(2),
            ]);
    }
}
