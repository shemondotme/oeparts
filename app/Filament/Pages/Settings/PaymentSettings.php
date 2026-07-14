<?php

namespace App\Filament\Pages\Settings;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Http;

class PaymentSettings extends SettingsPage
{
    protected static ?string $title = 'Payment Settings';

    protected static string $settingsGroup = 'payment';

    protected static ?int $navigationSort = 16;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testAirwallex')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Test Airwallex Gateway')
                ->modalDescription('Pings the Airwallex API to verify your Client ID and API key are valid. No charges are made.')
                ->modalSubmitActionLabel('Test Now')
                ->action(function () {
                    $clientId = $this->data['airwallex_client_id'] ?? null;
                    $apiKey = $this->data['airwallex_api_key'] ?? null;
                    $env = $this->data['airwallex_environment'] ?? 'sandbox';

                    if (! $clientId || ! $apiKey) {
                        Notification::make()
                            ->title('Missing credentials')
                            ->body('Please fill in both Client ID and API Private Key before testing.')
                            ->warning()
                            ->send();

                        return;
                    }

                    // Must match PaymentService::createAirwallexIntent() and
                    // CheckoutController::paymentIntent()'s own check ('live') —
                    // this previously checked 'production', a value neither of
                    // those ever writes/reads, so selecting "Production (Live
                    // Mode)" below silently never switched the actual checkout
                    // off the sandbox API. Confirmed via a live sandbox test run.
                    $baseUrl = $env === 'live'
                        ? 'https://api.airwallex.com'
                        : 'https://api-demo.airwallex.com';

                    try {
                        // Airwallex has no GET /authentication/query endpoint — the
                        // real (and only) way to verify credentials is the same
                        // login exchange every other call needs: POST
                        // /authentication/login with x-client-id/x-api-key headers,
                        // returning a bearer token on success. Confirmed against
                        // the real sandbox API directly (this previously hit a
                        // nonexistent endpoint and would report "Connection
                        // failed" even for valid credentials).
                        $response = Http::withHeaders([
                            'x-client-id' => $clientId,
                            'x-api-key' => $apiKey,
                            'Content-Type' => 'application/json',
                        ])->timeout(10)->post("{$baseUrl}/api/v1/authentication/login", (object) []);

                        if ($response->successful()) {
                            Notification::make()
                                ->title('Connection successful')
                                ->body("Airwallex {$env} API responded OK.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connection failed')
                                ->body("API returned HTTP {$response->status()}: " . $response->body())
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Connection error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Airwallex Payment Gateway')
                    ->description('Card gateway configurations. Key secrets are securely encrypted in the database.')
                    ->schema([
                        Forms\Components\Select::make('airwallex_environment')
                            ->label('Gateway API Environment')
                            ->options([
                                'sandbox' => 'Sandbox (Testing Mode)',
                                // Value must be 'live' — PaymentService and
                                // CheckoutController both check for exactly
                                // that string, not 'production'.
                                'live' => 'Production (Live Mode)',
                            ])
                            ->default('sandbox'),

                        Forms\Components\TextInput::make('airwallex_client_id')
                            ->label('Client ID Key')
                            ->placeholder('e.g. client_xxxxxx')
                            ->maxLength(255)
                            ->default(null),

                        Forms\Components\TextInput::make('airwallex_api_key')
                            ->label('API Private Key')
                            ->password()
                            ->revealable()
                            ->helperText('Saved encrypted in database')
                            ->default(null),

                        Forms\Components\TextInput::make('airwallex_webhook_secret')
                            ->label('Webhook Signoff Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Saved encrypted in database')
                            ->default(null),
                    ])->columns(2),

                Section::make('B2B Offline Bank Transfer')
                    ->description('Set institutional credentials for processing B2B bank wire orders.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Recipient Institution Name')
                            ->placeholder('e.g. SEB Bankas')
                            ->maxLength(255)
                            ->default(null),

                        Forms\Components\TextInput::make('bank_iban')
                            ->label('IBAN Account Number')
                            ->placeholder('LT00 0000 0000 0000 0000')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\TextInput::make('bank_bic')
                            ->label('SWIFT / BIC Code')
                            ->placeholder('e.g. CBVILT2X')
                            ->maxLength(50)
                            ->default(null),

                        Forms\Components\TextInput::make('bank_account_holder')
                            ->label('Entity Account Holder Name')
                            ->placeholder('UAB OeParts Europe')
                            ->maxLength(255)
                            ->default(null),

                        Forms\Components\TextInput::make('bank_reference_prefix')
                            ->label('Reference Verification Prefix')
                            ->maxLength(20)
                            ->helperText('Prefix string automatically prepended to buyer references (e.g. OEM-1002)')
                            ->default('OEM'),
                    ])->columns(2),

                Section::make('Storefront Payment Methods')
                    ->description('Which payment methods appear at checkout is controlled on the Checkout Settings page.')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_methods_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new \Illuminate\Support\HtmlString(
                                'Enable or disable card / bank-transfer checkout on the <a href="'
                                . CheckoutSettings::getUrl()
                                . '" class="fi-link text-primary-600">Checkout Settings</a> page → Allowed Payment Methods.'
                            )),
                    ]),
            ]);
    }
}
