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
            ...parent::getHeaderActions(),
            Action::make('testAirwallex')
                ->label('Test Airwallex Connection')
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
            Action::make('testPaysera')
                ->label('Test Paysera Connection')
                ->icon('heroicon-o-signal')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Test Paysera Gateway')
                ->modalDescription('Requests an OAuth2 token from Paysera to verify your Client ID and Client Secret are valid. No charges are made.')
                ->modalSubmitActionLabel('Test Now')
                ->action(function () {
                    $clientId = $this->data['paysera_client_id'] ?? null;
                    $clientSecret = $this->data['paysera_client_secret'] ?? null;

                    if (! $clientId || ! $clientSecret) {
                        Notification::make()
                            ->title('Missing credentials')
                            ->body('Please fill in both Client ID and Client Secret before testing.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $response = Http::asForm()->timeout(10)->post(
                            'https://api.paysera.com/auth/realms/Paysera/protocol/openid-connect/token',
                            [
                                'grant_type' => 'client_credentials',
                                'client_id' => $clientId,
                                'client_secret' => $clientSecret,
                            ]
                        );

                        if ($response->successful() && $response->json('access_token')) {
                            Notification::make()
                                ->title('Connection successful')
                                ->body('Paysera API responded OK with a valid access token.')
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

                        Forms\Components\TextInput::make('airwallex_merchant_country_code')
                            ->label('Merchant Country Code')
                            ->placeholder('e.g. LT')
                            ->helperText('ISO 3166-1 alpha-2 code of the country transactions are processed from. Required by Airwallex to offer Apple Pay / Google Pay in the Drop-in element.')
                            ->maxLength(2)
                            ->default('LT'),

                        Forms\Components\Toggle::make('airwallex_manual_capture_enabled')
                            ->label('Hold Funds Until Shipment (Manual Capture)')
                            ->helperText('When on, card payments are authorized (held) at checkout but not charged. The customer\'s card is only actually charged once the order is marked Shipped — or earlier via "Capture Payment" on the order. Authorizations expire after ~7 days if never captured, so don\'t leave orders unshipped indefinitely. Off by default: card payments are charged immediately at checkout, as before.')
                            ->columnSpanFull()
                            ->default(false),
                    ])->columns(2),

                Section::make('Paysera Payment Gateway')
                    ->description('Paysera Checkout Modern card gateway. Key secrets are securely encrypted in the database.')
                    ->schema([
                        Forms\Components\Select::make('paysera_environment')
                            ->label('Gateway API Environment')
                            ->options([
                                'sandbox' => 'Sandbox (Testing Mode)',
                                'live' => 'Production (Live Mode)',
                            ])
                            ->helperText('Paysera serves both sandbox and live from the same API host — this only labels which Client ID / Secret pair you have configured below.')
                            ->default('sandbox'),

                        Forms\Components\TextInput::make('paysera_client_id')
                            ->label('Client ID')
                            ->placeholder('e.g. 12345abc-...')
                            ->maxLength(255)
                            ->default(null),

                        Forms\Components\TextInput::make('paysera_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Saved encrypted in database')
                            ->default(null),

                        Forms\Components\TextInput::make('paysera_webhook_secret')
                            ->label('Webhook Signoff Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Saved encrypted in database. Best-effort HMAC verification — confirm the exact scheme against Paysera\'s real callback delivery once available.')
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
                                'Enable or disable card / Paysera / bank-transfer checkout on the <a href="'
                                . CheckoutSettings::getUrl()
                                . '" class="fi-link text-primary-600">Checkout Settings</a> page → Allowed Payment Methods.'
                            )),
                    ]),
            ]);
    }
}
