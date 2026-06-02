<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class PaymentSettings extends SettingsPage
{
    protected static ?string $title = 'Payment Settings';

    protected static string $settingsGroup = 'payment';

    protected static ?int $navigationSort = 16;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Airwallex')
                    ->schema([
                        Forms\Components\Select::make('airwallex_environment')
                            ->label('Environment')
                            ->options([
                                'sandbox' => 'Sandbox',
                                'production' => 'Production',
                            ])
                            ->default('sandbox'),
                        Forms\Components\TextInput::make('airwallex_client_id')
                            ->label('Client ID')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('airwallex_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted at rest')
                            ->default(null),
                        Forms\Components\TextInput::make('airwallex_webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Stored encrypted at rest')
                            ->default(null),
                    ])->columns(2),

                Section::make('Bank Transfer')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('bank_iban')
                            ->label('IBAN')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('bank_bic')
                            ->label('BIC/SWIFT')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('bank_account_holder')
                            ->label('Account Holder')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('bank_reference_prefix')
                            ->label('Payment Reference Prefix')
                            ->maxLength(20)
                            ->default('OEM'),
                    ])->columns(2),

                Section::make('Payment Methods')
                    ->schema([
                        Forms\Components\Toggle::make('card_enabled')
                            ->label('Enable Card Payments')
                            ->default(true),
                        Forms\Components\Toggle::make('bank_transfer_enabled')
                            ->label('Enable Bank Transfer')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
