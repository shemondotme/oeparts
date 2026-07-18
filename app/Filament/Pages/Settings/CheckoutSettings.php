<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

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

                        Forms\Components\Toggle::make('enable_apple_pay')
                            ->label('Enable Apple Pay')
                            ->helperText('Shown inside the Card option at checkout when on. Requires Apple Pay to already be enabled and domain-verified on your Airwallex merchant account — this toggle only controls whether the storefront offers it.')
                            ->default(true),

                        Forms\Components\Toggle::make('enable_google_pay')
                            ->label('Enable Google Pay')
                            ->helperText('Shown inside the Card option at checkout when on. Requires Google Pay to already be enabled on your Airwallex merchant account — this toggle only controls whether the storefront offers it.')
                            ->default(true),
                    ])->columns(2),

                Section::make('Rush Processing Upsell')
                    ->description('Customer-facing paid fast-track option offered at checkout, alongside shipping method selection.')
                    ->schema([
                        Forms\Components\Toggle::make('urgent_processing_enabled')
                            ->label('Offer Rush Processing at Checkout')
                            ->helperText('When on, customers can pay an extra fee to have their order flagged Urgent (same-day dispatch priority) — the same flag operators already set manually from the order view.')
                            ->live()
                            ->default(false),

                        Forms\Components\TextInput::make('urgent_processing_fee')
                            ->label('Rush Processing Fee')
                            ->numeric()
                            ->prefix('€')
                            ->minValue(0)
                            ->step(0.01)
                            ->required()
                            ->visible(fn (Get $get) => $get('urgent_processing_enabled'))
                            ->default(9.99),

                    ])->columns(2),

                Section::make('Rush Processing — Customer-Facing Copy')
                    ->description('Shown at checkout in the customer\'s own language. Leave a locale blank to fall back to English.')
                    ->visible(fn (Get $get) => $get('urgent_processing_enabled'))
                    ->schema([
                        AdminUi::translatableTabs('Rush Processing Copy', [
                            'urgent_processing_label' => [
                                'label' => 'Checkout Option Label',
                                'required' => true,
                                'maxLength' => 100,
                                'placeholder' => 'Rush processing',
                            ],
                            'urgent_processing_description' => [
                                'label' => 'Checkout Option Description',
                                'type' => 'textarea',
                                'rows' => 2,
                                'maxLength' => 300,
                                'placeholder' => 'Priority same-day dispatch for orders placed before 2pm on a business day.',
                            ],
                        ]),
                    ]),

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

                        Forms\Components\TextInput::make('proof_max_size_kb')
                            ->label('Payment Proof Max File Size (KB)')
                            ->numeric()
                            ->minValue(100)
                            ->maxValue(20480)
                            ->required()
                            ->helperText('Maximum upload size for bank-transfer payment proof attachments')
                            ->default(5120),

                        Forms\Components\TextInput::make('guest_password_length')
                            ->label('Guest Account Generated Password Length')
                            ->numeric()
                            ->minValue(8)
                            ->maxValue(64)
                            ->required()
                            ->helperText('Length of the random password generated for guest-checkout accounts')
                            ->default(12),
                    ])->columns(2),
            ]);
    }
}
