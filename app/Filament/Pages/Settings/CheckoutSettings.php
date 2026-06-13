<?php

namespace App\Filament\Pages\Settings;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class CheckoutSettings extends SettingsPage
{
    protected static ?string $title = 'Checkout Settings';

    protected static string $settingsGroup = 'checkout';

    protected static ?int $navigationSort = 15;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Checkout Flow')
                    ->description('Configure checkout steps, timeouts, and payment defaults.')
                    ->schema([
                        Forms\Components\Select::make('default_payment_method')
                            ->label('Default Payment Method')
                            ->options([
                                'card' => 'Card',
                                'bank_transfer' => 'Bank Transfer',
                            ])
                            ->default('card')
                            ->native(false),

                        Forms\Components\TextInput::make('timeout_minutes')
                            ->label('Checkout Timeout (minutes)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(120)
                            ->default(30),

                        Forms\Components\TextInput::make('max_steps')
                            ->label('Maximum Checkout Steps')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(7)
                            ->default(5),
                    ])->columns(2),

                Section::make('Payment Methods')
                    ->description('Enable or disable specific payment methods.')
                    ->schema([
                        Forms\Components\TagsInput::make('allowed_payment_methods')
                            ->label('Allowed Payment Methods')
                            ->helperText('Enter: card, bank_transfer')
                            ->default(['card', 'bank_transfer']),
                    ])->columns(2),

                Section::make('Customer Messages')
                    ->description('Customize checkout feedback messages.')
                    ->schema([
                        Forms\Components\Textarea::make('payment_success_message')
                            ->label('Payment Success Message')
                            ->rows(2)
                            ->default('Payment processing initiated. You will be redirected shortly.'),

                        Forms\Components\Textarea::make('payment_error_message')
                            ->label('Payment Error Message')
                            ->rows(2)
                            ->default('Payment method is not supported. Please try another method.'),
                    ])->columns(1),

                Section::make('Order Limits')
                    ->description('Enforce minimum and maximum order thresholds.')
                    ->schema([
                        Forms\Components\TextInput::make('max_note_length')
                            ->label('Customer Note Max Length')
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(2000)
                            ->default(500),
                    ])->columns(2),
            ]);
    }
}
