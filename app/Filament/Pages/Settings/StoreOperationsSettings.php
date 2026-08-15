<?php

namespace App\Filament\Pages\Settings;

use App\Enums\SettingType;
use App\Filament\Resources\ShippingZoneResource;
use App\Filament\Resources\TaxRateResource;
use App\Filament\Support\AdminUi;
use App\Jobs\SendTestEmailJob;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Largest merge in the settings reorg — 12 groups behind one page: orders,
 * customers, cart, dashboard, shipping, tax, checkout, payment, email,
 * contact, part_inquiry, invoice (new — see invoiceTab()). Same
 * $settingsGroups multi-group override pattern as SeoControlCenter /
 * SecurityAccessSettings. Checkout+Payment share one tab (both edit the
 * checkout experience); Contact+Part Inquiry share one tab (both are
 * customer-facing support channels). Rush Processing Upsell (originally
 * checkout.urgent_processing_*) moved to its own 'rush_upsell' group on
 * the Marketing settings page in Phase 5 — the Checkout & Payments tab
 * here only carries a cross-link note forward, not the fields themselves.
 *
 * Header actions (Test Airwallex / Test Paysera / Send Test Email) are
 * carried forward from PaymentSettings/EmailSettings verbatim — Filament
 * header actions are page-level, not tab-level, so all three now render
 * regardless of which tab is active (an accepted UX tradeoff of the merge,
 * matching how SeoControlCenter's regenerate-sitemap action already works
 * the same way).
 */
class StoreOperationsSettings extends SettingsPage
{
    protected static ?string $title = 'Store Operations';

    protected static string $settingsGroup = 'orders';

    /** @var string[] */
    protected static array $settingsGroups = [
        'orders', 'customers', 'cart', 'dashboard', 'shipping', 'tax',
        'checkout', 'payment', 'email', 'contact', 'part_inquiry', 'invoice',
    ];

    protected static ?int $navigationSort = 10;

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-shopping-bag';
    }

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

                    $baseUrl = $env === 'live'
                        ? 'https://api.airwallex.com'
                        : 'https://api-demo.airwallex.com';

                    try {
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
            Action::make('testEmail')
                ->label('Send Test Email')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send Test Email')
                ->modalDescription('This will send a test email to the admin recipient using your current SMTP settings.')
                ->modalSubmitActionLabel('Send Now')
                ->action(function () {
                    $adminEmail = $this->data['admin_notify_email'] ?? $this->data['from_address'] ?? null;

                    if (! $adminEmail) {
                        Notification::make()
                            ->title('No recipient')
                            ->body('Please set an admin notification email or sender email first.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        SendTestEmailJob::dispatch($adminEmail);

                        Notification::make()
                            ->title('Test email queued')
                            ->body("Queued for {$adminEmail}. Check your inbox shortly.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Email failed')
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
                Tabs::make('Store Operations')
                    ->columnSpanFull()
                    ->tabs([
                        $this->ordersPolicyTab(),
                        $this->customersTab(),
                        $this->cartRulesTab(),
                        $this->dashboardThresholdsTab(),
                        $this->shippingTab(),
                        $this->taxTab(),
                        $this->checkoutPaymentsTab(),
                        $this->emailSetupTab(),
                        $this->customerServiceTab(),
                        $this->invoiceTab(),
                    ]),
            ]);
    }

    private function ordersPolicyTab(): Tab
    {
        return Tab::make('Orders Policy')
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                Section::make('Order Processing Windows & Policies')
                    ->description('Govern cancellation windows, refund defaults, bank transfer expiration, and checkout thresholds.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_transfer_expiry_hours')
                            ->label('Bank Transfer Payment Limit (Hours)')
                            ->numeric()->minValue(1)->maxValue(720)->required()
                            ->helperText('Hours pending bank orders stay active before automatic expiry cancellation')
                            ->default(48),

                        Forms\Components\TextInput::make('customer_cancel_window_hours')
                            ->label('Client Cancel Grace Period (Hours)')
                            ->numeric()->minValue(1)->maxValue(168)->required()
                            ->helperText('Hours buyers can cancel their order directly from their account portal')
                            ->default(2),

                        Forms\Components\TextInput::make('refund_window_days')
                            ->label('Portal RMA Return Limit (Days)')
                            ->numeric()->minValue(1)->maxValue(90)->required()
                            ->helperText('Days from order fulfillment that buyers can request returns/refunds')
                            ->default(14),

                        Forms\Components\TextInput::make('minimum_order_amount')
                            ->label('Minimum Store Order Value (€)')
                            ->numeric()->minValue(0)->prefix('€')
                            ->helperText('Set to 0 to disable minimum order requirement')
                            ->default(0),

                        Forms\Components\TextInput::make('auto_complete_days')
                            ->label('Auto-Complete Order Fulfillment (Days)')
                            ->helperText('Shipped orders are automatically marked as delivered after this many days (runs nightly). Set to 0 to disable.')
                            ->numeric()->minValue(0)->maxValue(90)
                            ->default(14),
                    ])->columns(2),

                Section::make('Documents & Numbering Prefixes')
                    ->description('Set numbering patterns, prefix strings, and sequence padding digits.')
                    ->schema([
                        Forms\Components\TextInput::make('order_number_prefix')
                            ->label('Order Number Prefix')->maxLength(10)->placeholder('ORD')->default('ORD'),

                        Forms\Components\TextInput::make('order_number_padding')
                            ->label('Number Sequence Zero Padding')
                            ->numeric()->minValue(3)->maxValue(10)
                            ->helperText('Pad sequence digits with leading zeros (e.g. 6 digits = ORD-000123)')
                            ->default(6),

                        Forms\Components\TextInput::make('invoice_number_prefix')
                            ->label('Invoice Number Prefix')->maxLength(10)->placeholder('INV')->default('INV'),

                        Forms\Components\TextInput::make('rma_number_prefix')
                            ->label('RMA Return Ticket Prefix')->maxLength(10)->placeholder('RMA')->default('RMA'),
                    ])->columns(2),
            ]);
    }

    private function customersTab(): Tab
    {
        return Tab::make('Customers')
            ->icon('heroicon-o-users')
            ->schema([
                Section::make('Customer Segments')
                    ->description('Thresholds behind the Segment badge on the Customers list (VIP / Repeat / Regular / Prospect).')
                    ->schema([
                        Forms\Components\TextInput::make('vip_min_orders')
                            ->label('VIP — Minimum Orders')
                            ->numeric()->minValue(1)->default(10)
                            ->helperText('A customer needs at least this many orders (and the spend below) to rank as VIP.'),
                        Forms\Components\TextInput::make('vip_min_spent')
                            ->label('VIP — Minimum Total Spend (€)')
                            ->numeric()->minValue(0)->prefix('€')->default(1000)
                            ->helperText('Total paid-order spend required for VIP, together with the order count above.'),
                        Forms\Components\TextInput::make('repeat_min_orders')
                            ->label('Repeat — Minimum Orders')
                            ->numeric()->minValue(2)->default(3)
                            ->helperText('Customers with at least this many orders (but below VIP) rank as Repeat.'),
                    ])->columns(3),
            ]);
    }

    private function cartRulesTab(): Tab
    {
        return Tab::make('Cart Rules')
            ->icon('heroicon-o-shopping-cart')
            ->schema([
                Section::make('Cart & Checkout Operations')
                    ->description('Set rules for guest/portal cart timeouts, price discrepancy warnings, and merge settings.')
                    ->schema([
                        Forms\Components\TextInput::make('expiry_days')
                            ->label('Inactive Cart Lifespan (Days)')
                            ->numeric()->minValue(1)->maxValue(90)->required()
                            ->helperText('Number of days to preserve abandoned items before clearing from database')
                            ->default(7),

                        Forms\Components\TextInput::make('max_items')
                            ->label('Maximum Unique Items per Cart')
                            ->numeric()->minValue(1)->maxValue(500)->required()
                            ->helperText('Prevents excessive load and protects checkout routes')
                            ->default(50),

                        Forms\Components\TextInput::make('price_change_threshold')
                            ->label('Price Change Alert Threshold (%)')
                            ->helperText('Warn customer if price of an item in cart changes by this percent or more')
                            ->numeric()->minValue(1)->maxValue(100)->suffix('%')->required()
                            ->default(20),

                        Forms\Components\Placeholder::make('checkout_timeout_minutes_note')
                            ->label('')
                            ->content('Checkout session timeout is set on the Checkout & Payments tab above → Checkout Timeout (minutes).'),

                        Forms\Components\Toggle::make('otp_required_guest')
                            ->label('Require OTP for Guest Checkouts')
                            ->helperText('Sends verification code to guest email during purchase steps')
                            ->default(true),

                        Forms\Components\Toggle::make('coupon_enabled')
                            ->label('Enable Checkout Coupons')
                            ->helperText('Allows entering promo/coupon codes on shopping cart page')
                            ->default(true),

                        Forms\Components\Toggle::make('merge_on_login')
                            ->label('Merge Guest Cart on Login')
                            ->helperText('Combines guest cart items with logged-in user cart items')
                            ->default(true),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Cart Add Rate Limit (per Minute)')
                            ->numeric()->minValue(1)->maxValue(600)->required()
                            ->helperText('Maximum cart-add requests allowed per minute, per client')
                            ->default(60),

                        Forms\Components\TextInput::make('max_quantity')
                            ->label('Maximum Quantity per Line Item')
                            ->numeric()->minValue(1)->maxValue(10000)->required()
                            ->helperText('Maximum quantity of a single product allowed in one cart line')
                            ->default(999),

                        Forms\Components\TextInput::make('guest_cookie_days')
                            ->label('Guest Cart Cookie Lifetime (Days)')
                            ->numeric()->minValue(1)->maxValue(90)->required()
                            ->helperText('How long the guest cart identifier cookie persists')
                            ->default(7),
                    ])->columns(2),
            ]);
    }

    private function dashboardThresholdsTab(): Tab
    {
        return Tab::make('Dashboard Alert Thresholds')
            ->icon('heroicon-o-presentation-chart-line')
            ->schema([
                Section::make('Admin Dashboard Alert Thresholds')
                    ->description('Tune the warning thresholds used by dashboard widgets to flag attention-needed orders and abandoned carts.')
                    ->schema([
                        Forms\Components\TextInput::make('orders_threshold')
                            ->label('Pending Orders Alert Threshold')
                            ->numeric()->minValue(1)->maxValue(10000)->required()
                            ->helperText('Pending order count that triggers the dashboard attention indicator')
                            ->default(50),

                        Forms\Components\TextInput::make('pending_delayed_minutes')
                            ->label('Delayed Order Threshold (Minutes)')
                            ->numeric()->minValue(1)->maxValue(1440)->required()
                            ->helperText('Minutes a pending order can sit before being flagged as delayed')
                            ->default(120),

                        Forms\Components\TextInput::make('cart_abandoned_hours')
                            ->label('Cart Abandonment Threshold (Hours)')
                            ->numeric()->minValue(1)->maxValue(720)->required()
                            ->helperText('Hours of inactivity before a cart counts as abandoned — drives the dashboard widget and the hourly recovery-email run')
                            ->default(2),
                    ])->columns(2),

                Section::make('Health Check Thresholds')
                    ->description('Tune when the System → Health Check page flags the scheduler or backups as stale.')
                    ->schema([
                        Forms\Components\TextInput::make('scheduler_stale_minutes')
                            ->label('Scheduler Heartbeat Stale Threshold (Minutes)')
                            ->numeric()->minValue(1)->maxValue(60)->required()
                            ->helperText('Minutes since the last cron heartbeat before the Scheduler check is flagged stale')
                            ->default(3),

                        Forms\Components\TextInput::make('backup_stale_hours')
                            ->label('Backup Stale Threshold (Hours)')
                            ->numeric()->minValue(1)->maxValue(168)->required()
                            ->helperText('Hours since the last successful backup before the Backup check is flagged stale')
                            ->default(26),
                    ])->columns(2),

                Section::make('Cache Dashboard Threshold')
                    ->description('Tune when the System → Cache Dashboard page alerts admins about a degraded hit rate.')
                    ->schema([
                        Forms\Components\TextInput::make('cache_hit_rate_alert_threshold')
                            ->label('Hit Rate Alert Threshold (%)')
                            ->numeric()->minValue(1)->maxValue(100)->required()
                            ->helperText('Admins are notified once when the Redis cache hit rate drops below this percentage')
                            ->default(50),
                    ])->columns(2),
            ]);
    }

    private function shippingTab(): Tab
    {
        return Tab::make('Shipping')
            ->icon('heroicon-o-truck')
            ->schema([
                Section::make('Shipping Thresholds & Fees')
                    ->description('Set nudge goals and basic handling charges. Free shipping thresholds are set per shipping method.')
                    ->schema([
                        Forms\Components\Placeholder::make('free_shipping_threshold_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Free shipping thresholds are set per shipping method, not globally — configure them on the <a href="'
                                . ShippingZoneResource::getUrl()
                                . '" class="fi-link text-primary-600">Shipping Zones</a> page (each method has its own "Free Shipping Threshold" field).'
                            )),

                        Forms\Components\TextInput::make('handling_fee')
                            ->label('Standard Order Handling Fee')
                            ->numeric()->minValue(0)->prefix('€')->placeholder('0.00')->default(0),

                        Forms\Components\Toggle::make('nudge_enabled')
                            ->label('Enable Free Shipping Nudge Alert')
                            ->helperText('Displays a notice in cart showing how much more is needed for free shipping')
                            ->default(true),

                        Forms\Components\TextInput::make('nudge_threshold')
                            ->label('Nudge Trigger Remaining Amount')
                            ->numeric()->minValue(0)->prefix('€')->placeholder('10')
                            ->helperText('Trigger nudge only when remaining amount is below this value')
                            ->default(10),

                        AdminUi::translatableTabs('Free Shipping Nudge Text', [
                            'nudge_text' => [
                                'label' => 'Nudge',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'Add only €{amount} more to get free shipping!',
                                    'de' => 'Fügen Sie noch €{amount} hinzu, um kostenlosen Versand zu erhalten!',
                                    'lt' => 'Pridėkite dar €{amount} nemokamam pristatymui!',
                                    'fr' => 'Ajoutez €{amount} de plus pour la livraison gratuite !',
                                    'es' => '¡Añade €{amount} más para conseguir envío gratis!',
                                ],
                            ],
                        ]),

                        AdminUi::translatableTabs('Shipping Note (checkout step 3)', [
                            'note_text' => [
                                'label' => 'Note',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'All shipments tracked and insured. Delivery times are estimates from dispatch.',
                                    'de' => 'Alle Sendungen werden verfolgt und sind versichert. Lieferzeiten sind Schätzungen ab Versand.',
                                    'lt' => 'Visos siuntos sekamos ir apdraustos. Pristatymo laikas skaičiuojamas nuo išsiuntimo.',
                                    'fr' => 'Tous les envois sont suivis et assurés. Les délais de livraison sont estimés à partir de l\'expédition.',
                                    'es' => 'Todos los envíos son rastreados y están asegurados. Los plazos de entrega son estimaciones desde el envío.',
                                ],
                            ],
                        ]),
                    ])->columns(2),

                Section::make('Business Days & Origin')
                    ->description('Define which days are considered business days and the default origin country for shipping calculations.')
                    ->schema([
                        Forms\Components\TagsInput::make('business_days')
                            ->label('Business Days')
                            ->helperText('Days when orders are processed and shipped. Used for estimated delivery calculations.')
                            ->suggestions(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])
                            ->default(['Mon', 'Tue', 'Wed', 'Thu', 'Fri']),

                        Forms\Components\Select::make('default_origin_country')
                            ->label('Default Origin Country')
                            ->helperText('Used as the default ship-from country for rate calculations.')
                            ->options([
                                'LT' => 'Lithuania', 'DE' => 'Germany', 'PL' => 'Poland', 'LV' => 'Latvia',
                                'EE' => 'Estonia', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'FR' => 'France',
                                'ES' => 'Spain', 'IT' => 'Italy', 'CZ' => 'Czech Republic', 'AT' => 'Austria',
                                'SE' => 'Sweden', 'FI' => 'Finland', 'IE' => 'Ireland', 'PT' => 'Portugal',
                                'SI' => 'Slovenia', 'SK' => 'Slovakia', 'HU' => 'Hungary', 'HR' => 'Croatia',
                                'BG' => 'Bulgaria', 'RO' => 'Romania', 'DK' => 'Denmark', 'NO' => 'Norway',
                                'CH' => 'Switzerland', 'GB' => 'United Kingdom',
                            ])
                            ->searchable()
                            ->default('LT'),
                    ])->columns(2),

                Section::make('Fulfillment Cutoffs')
                    ->description('Specify operational cutoff rules for daily shipments.')
                    ->schema([
                        Forms\Components\TimePicker::make('cutoff_time')
                            ->label('Fulfillment Cutoff Time')
                            ->helperText('Orders placed after this time are processed the next business day')
                            ->default('15:00'),

                        Forms\Components\Select::make('cutoff_timezone')
                            ->label('Operational Timezone')
                            ->options([
                                'Europe/Vilnius' => 'Europe/Vilnius (UTC+2)',
                                'Europe/Berlin' => 'Europe/Berlin (UTC+1)',
                                'Europe/Paris' => 'Europe/Paris (UTC+1)',
                                'Europe/Madrid' => 'Europe/Madrid (UTC+1)',
                                'Europe/London' => 'Europe/London (UTC+0)',
                                'Europe/Rome' => 'Europe/Rome (UTC+1)',
                                'Europe/Amsterdam' => 'Europe/Amsterdam (UTC+1)',
                                'Europe/Warsaw' => 'Europe/Warsaw (UTC+1)',
                                'Europe/Prague' => 'Europe/Prague (UTC+1)',
                                'Europe/Stockholm' => 'Europe/Stockholm (UTC+1)',
                                'Europe/Copenhagen' => 'Europe/Copenhagen (UTC+1)',
                                'Europe/Helsinki' => 'Europe/Helsinki (UTC+2)',
                                'Europe/Riga' => 'Europe/Riga (UTC+2)',
                                'Europe/Tallinn' => 'Europe/Tallinn (UTC+2)',
                                'UTC' => 'UTC',
                            ])
                            ->searchable()
                            ->default('Europe/Vilnius'),
                    ])->columns(2),
            ]);
    }

    private function taxTab(): Tab
    {
        return Tab::make('Tax')
            ->icon('heroicon-o-calculator')
            ->schema([
                Section::make('EU VAT Configurations')
                    ->description('Specify standard VAT percents and localized country rates. Integrates automatically with VAT verification services.')
                    ->schema([
                        Forms\Components\Placeholder::make('company_vat_number_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Your own company VAT registration number (printed on generated customer invoices) is set on the <a href="'
                                . GeneralBrandSettings::getUrl()
                                . '" class="fi-link text-primary-600">General & Brand</a> page\'s Company & Legal tab, alongside your registered address and contact details.'
                            )),

                        Forms\Components\TextInput::make('default_vat_rate')
                            ->label('Default Store VAT Percent (%)')
                            ->numeric()->minValue(0)->maxValue(100)->suffix('%')->required()
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
                            ->visible(fn (Get $get): bool => (bool) $get('country_based_vat_enabled'))
                            ->content(new HtmlString(
                                'Manage per-country rates on the <a href="'
                                . TaxRateResource::getUrl('index')
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

    private function checkoutPaymentsTab(): Tab
    {
        return Tab::make('Checkout & Payments')
            ->icon('heroicon-o-credit-card')
            ->schema([
                Section::make('Checkout Flow')
                    ->description('Configure checkout steps, timeouts, and payment defaults.')
                    ->schema([
                        Forms\Components\Select::make('default_payment_method')
                            ->label('Default Payment Method')
                            ->options([
                                'card' => 'Card',
                                'paysera' => 'Paysera',
                                'bank_transfer' => 'Bank Transfer',
                            ])
                            ->default('card')
                            ->native(false),

                        Forms\Components\TextInput::make('timeout_minutes')
                            ->label('Checkout Timeout (minutes)')
                            ->numeric()->minValue(5)->maxValue(120)->default(30),

                        Forms\Components\TextInput::make('max_steps')
                            ->label('Maximum Checkout Steps')
                            ->numeric()->minValue(3)->maxValue(7)->default(5),
                    ])->columns(2),

                Section::make('Payment Methods')
                    ->description('Enable or disable specific payment methods.')
                    ->schema([
                        Forms\Components\TagsInput::make('allowed_payment_methods')
                            ->label('Allowed Payment Methods')
                            ->helperText('Enter: card, paysera, bank_transfer')
                            ->default(['card', 'paysera', 'bank_transfer']),

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
                        Forms\Components\Placeholder::make('rush_upsell_moved_note')
                            ->label('')
                            ->columnSpanFull()
                            ->content(new HtmlString(
                                'Rush Processing Upsell (enable/fee/customer-facing copy) moved to the <a href="'
                                . MarketingSettings::getUrl()
                                . '" class="fi-link text-primary-600">Marketing</a> page\'s own tab — it\'s an upsell lever, not a checkout mechanic.'
                            )),
                    ]),

                Section::make('Customer Messages')
                    ->description('Customize checkout feedback messages.')
                    ->schema([
                        Forms\Components\Textarea::make('payment_success_message')
                            ->label('Payment Success Message')->rows(2)
                            ->default('Payment processing initiated. You will be redirected shortly.'),

                        Forms\Components\Textarea::make('payment_error_message')
                            ->label('Payment Error Message')->rows(2)
                            ->default('Payment method is not supported. Please try another method.'),
                    ])->columns(1),

                Section::make('Order Limits')
                    ->description('Enforce minimum and maximum order thresholds.')
                    ->schema([
                        Forms\Components\TextInput::make('max_note_length')
                            ->label('Customer Note Max Length')
                            ->numeric()->minValue(100)->maxValue(2000)->default(500),

                        Forms\Components\TextInput::make('proof_max_size_kb')
                            ->label('Payment Proof Max File Size (KB)')
                            ->numeric()->minValue(100)->maxValue(20480)->required()
                            ->helperText('Maximum upload size for bank-transfer payment proof attachments')
                            ->default(5120),

                        Forms\Components\TextInput::make('guest_password_length')
                            ->label('Guest Account Generated Password Length')
                            ->numeric()->minValue(8)->maxValue(64)->required()
                            ->helperText('Length of the random password generated for guest-checkout accounts')
                            ->default(12),
                    ])->columns(2),

                Section::make('Airwallex Payment Gateway')
                    ->description('Card gateway configurations. Key secrets are securely encrypted in the database.')
                    ->schema([
                        Forms\Components\Select::make('airwallex_environment')
                            ->label('Gateway API Environment')
                            ->options([
                                'sandbox' => 'Sandbox (Testing Mode)',
                                'live' => 'Production (Live Mode)',
                            ])
                            ->default('sandbox'),

                        Forms\Components\TextInput::make('airwallex_client_id')
                            ->label('Client ID Key')
                            ->placeholder('e.g. client_xxxxxx')->maxLength(255)->default(null),

                        Forms\Components\TextInput::make('airwallex_api_key')
                            ->label('API Private Key')
                            ->password()->revealable()
                            ->helperText('Saved encrypted in database')->default(null),

                        Forms\Components\TextInput::make('airwallex_webhook_secret')
                            ->label('Webhook Signoff Secret')
                            ->password()->revealable()
                            ->helperText('Saved encrypted in database')->default(null),

                        Forms\Components\TextInput::make('airwallex_merchant_country_code')
                            ->label('Merchant Country Code')
                            ->placeholder('e.g. LT')
                            ->helperText('ISO 3166-1 alpha-2 code of the country transactions are processed from. Required by Airwallex to offer Apple Pay / Google Pay in the Drop-in element.')
                            ->maxLength(2)->default('LT'),

                        Forms\Components\Toggle::make('airwallex_manual_capture_enabled')
                            ->label('Hold Funds Until Shipment (Manual Capture)')
                            ->helperText('When on, card payments are authorized (held) at checkout but not charged. The customer\'s card is only actually charged once the order is marked Shipped — or earlier via "Capture Payment" on the order. Authorizations expire after ~7 days if never captured, so don\'t leave orders unshipped indefinitely. Off by default: card payments are charged immediately at checkout, as before.')
                            ->columnSpanFull()->default(false),
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
                            ->placeholder('e.g. 12345abc-...')->maxLength(255)->default(null),

                        Forms\Components\TextInput::make('paysera_client_secret')
                            ->label('Client Secret')
                            ->password()->revealable()
                            ->helperText('Saved encrypted in database')->default(null),

                        Forms\Components\TextInput::make('paysera_webhook_secret')
                            ->label('Webhook Signoff Secret')
                            ->password()->revealable()
                            ->helperText('Saved encrypted in database. Best-effort HMAC verification — confirm the exact scheme against Paysera\'s real callback delivery once available.')
                            ->default(null),
                    ])->columns(2),

                Section::make('B2B Offline Bank Transfer')
                    ->description('Set institutional credentials for processing B2B bank wire orders.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Recipient Institution Name')
                            ->placeholder('e.g. SEB Bankas')->maxLength(255)->default(null),

                        Forms\Components\TextInput::make('bank_iban')
                            ->label('IBAN Account Number')
                            ->placeholder('LT00 0000 0000 0000 0000')->maxLength(50)->default(null),

                        Forms\Components\TextInput::make('bank_bic')
                            ->label('SWIFT / BIC Code')
                            ->placeholder('e.g. CBVILT2X')->maxLength(50)->default(null),

                        Forms\Components\TextInput::make('bank_account_holder')
                            ->label('Entity Account Holder Name')
                            ->placeholder('UAB OeParts Europe')->maxLength(255)->default(null),

                        Forms\Components\TextInput::make('bank_reference_prefix')
                            ->label('Reference Verification Prefix')
                            ->maxLength(20)
                            ->helperText('Prefix string automatically prepended to buyer references (e.g. OEM-1002)')
                            ->default('OEM'),
                    ])->columns(2),
            ]);
    }

    private function emailSetupTab(): Tab
    {
        return Tab::make('Email Setup')
            ->icon('heroicon-o-envelope')
            ->schema([
                Section::make('From Address Configuration')
                    ->description('Set general identities for outgoing emails.')
                    ->schema([
                        Forms\Components\TextInput::make('from_name')
                            ->label('Sender Name')->maxLength(255)->placeholder('OeParts Europe')->default('OeParts'),

                        Forms\Components\TextInput::make('from_address')
                            ->label('Sender Email (From)')
                            ->email()->maxLength(255)->placeholder('noreply@oeparts.lt')->default(null),

                        Forms\Components\TextInput::make('reply_to')
                            ->label('Reply-To Email Address')
                            ->email()->maxLength(255)->placeholder('info@oeparts.lt')->default(null),
                    ])->columns(2),

                Section::make('SMTP Server Details')
                    ->description('Configure SMTP mail service parameters. Credentials are saved encrypted at rest.')
                    ->schema([
                        Forms\Components\TextInput::make('smtp_host')
                            ->label('SMTP Server Host')->maxLength(255)->placeholder('smtp.mailgun.org')->default(null),

                        Forms\Components\TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()->minValue(1)->maxValue(65535)->placeholder('587')->default(587),

                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Connection Encryption')
                            ->options([
                                'tls' => 'TLS (Secure)',
                                'ssl' => 'SSL (Legacy)',
                                '' => 'None',
                            ])
                            ->default('tls'),

                        Forms\Components\TextInput::make('smtp_username')
                            ->label('SMTP Auth Username')->maxLength(255)->placeholder('postmaster@domain.com')->default(null),

                        Forms\Components\TextInput::make('smtp_password')
                            ->label('SMTP Auth Password')
                            ->password()->revealable()
                            ->helperText('Stored encrypted inside database')->default(null),
                    ])->columns(2),

                Section::make('Admin System Alerts')
                    ->description('Set up notifications for administrators when critical storefront events trigger.')
                    ->schema([
                        Forms\Components\Toggle::make('admin_notify_new_order')
                            ->label('Notify Admin on New Order')
                            ->helperText('Send warning email when a buyer completes a checkout')->default(true),

                        Forms\Components\Toggle::make('admin_notify_new_inquiry')
                            ->label('Notify Admin on New Parts Inquiry')
                            ->helperText('Send email when a client creates a custom quote inquiry')->default(true),

                        Forms\Components\TextInput::make('admin_notify_email')
                            ->label('Recipient Admin Email')
                            ->email()->maxLength(255)->placeholder('admin@oeparts.lt')
                            ->helperText('Defaults to Site Email if left blank')
                            ->columnSpanFull()->default(null),
                    ])->columns(2),
            ]);
    }

    private function customerServiceTab(): Tab
    {
        return Tab::make('Customer Service')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->schema([
                Section::make('Contact Details')
                    ->description('Store locations and public-facing contact endpoints.')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->helperText('Support/customer service phone. For the public header phone, use General & Brand → Public Contact Phone.')
                            ->tel()->maxLength(30)->placeholder('+370 600 00000')->default(null),

                        Forms\Components\TextInput::make('email')
                            ->label('Contact Email')
                            ->helperText('Support/customer service email. For the public header email, use General & Brand → Public Contact Email.')
                            ->email()->maxLength(255)->placeholder('support@oeparts.lt')->default(null),

                        Forms\Components\Textarea::make('address')
                            ->label('Business Address')
                            ->helperText('Support/business location address. For the registered address, use General & Brand → Registered Address.')
                            ->rows(3)->maxLength(1000)->placeholder('e.g. Ulonų g. 5, Vilnius, Lithuania')
                            ->columnSpanFull()->default(null),
                    ])->columns(2),

                Section::make('Business Hours')
                    ->description('Operating hours rendered across footer and contact pages.')
                    ->schema([
                        AdminUi::translatableTabs('Business Hours', [
                            'hours' => [
                                'label' => 'Hours',
                                'type' => 'textarea',
                                'rows' => 2,
                                'helperText' => '',
                                'placeholders' => [
                                    'en' => 'e.g. Mon-Fri: 8:00 - 17:00, Sat: Closed',
                                    'de' => 'e.g. Mo-Fr: 8:00 - 17:00, Sa: Geschlossen',
                                    'lt' => 'e.g. I-V: 8:00 - 17:00, VI: Nedirbame',
                                    'fr' => 'e.g. Lun-Ven: 8h00 - 17h00, Sam: Fermé',
                                    'es' => 'e.g. Lun-Vie: 8:00 - 17:00, Sáb: Cerrado',
                                ],
                            ],
                        ]),
                    ]),

                Section::make('Messaging Channels')
                    ->description('Quick chat links or hotline numbers for B2B buyer assistance.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp Number')
                            ->tel()->placeholder('+370 600 00000')->maxLength(30)->default(null),

                        Forms\Components\TextInput::make('viber')
                            ->label('Viber Number')
                            ->tel()->placeholder('+370 600 00000')->maxLength(30)->default(null),
                    ])->columns(2),

                Section::make('Social Profiles')
                    ->description('Links to verified company social pages.')
                    ->schema([
                        Forms\Components\Placeholder::make('social_profiles_note')
                            ->label('')
                            ->content(new HtmlString(
                                'Social profile links (Facebook, LinkedIn, and 4 more platforms), footer display, and icon style are managed on the <a href="'
                                . CustomizationSettings::getUrl()
                                . '" class="fi-link text-primary-600">Customization</a> page\'s Menus & Social tab.'
                            )),
                    ]),

                Section::make('Form Submission — Success Message')
                    ->description('Shown to the customer in their own language immediately after a successful submission. Leave a locale blank to fall back to English.')
                    ->schema([
                        AdminUi::translatableTabs('Success Message', [
                            'success_message' => [
                                'label' => 'Success Message',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'Your message has been sent successfully. We will get back to you soon.',
                                    'de' => 'Ihre Nachricht wurde erfolgreich gesendet. Wir melden uns in Kürze bei Ihnen.',
                                    'lt' => 'Jūsų žinutė sėkmingai išsiųsta. Netrukus su jumis susisieksime.',
                                    'fr' => 'Votre message a été envoyé avec succès. Nous reviendrons vers vous sous peu.',
                                    'es' => 'Su mensaje se ha enviado correctamente. Nos pondremos en contacto con usted en breve.',
                                ],
                            ],
                        ]),
                    ]),

                Section::make('Abuse Protection')
                    ->description('Rate limiting for contact form submissions.')
                    ->schema([
                        Forms\Components\TextInput::make('form_rate_limit_per_minute')
                            ->label('Rate Limit (submissions / minute / IP)')
                            ->helperText('Maximum contact form submissions allowed per IP address per minute.')
                            ->numeric()->minValue(1)->maxValue(100)->default(5),
                    ]),

                Section::make('Inquiry Configuration')
                    ->description('Configure how part inquiries are handled and response expectations.')
                    ->schema([
                        Forms\Components\TextInput::make('response_hours')
                            ->label('Expected Response Time (hours)')
                            ->numeric()->minValue(1)->maxValue(168)
                            ->helperText('Displayed to customers as estimated response time')
                            ->default(24),

                        Forms\Components\Toggle::make('guest_inquiries_allowed')
                            ->label('Allow Guest Inquiries')
                            ->helperText('Allow non-registered users to submit part inquiries')
                            ->default(true),

                        Forms\Components\TextInput::make('rate_limit_per_hour')
                            ->label('Max Inquiries Per IP Per Hour')
                            ->numeric()->minValue(1)->maxValue(100)->default(10),
                    ])->columns(2),
            ]);
    }

    private function invoiceTab(): Tab
    {
        return Tab::make('Invoice')
            ->icon('heroicon-o-document-currency-euro')
            ->schema([
                Section::make('Invoice PDF')
                    ->description('Controls the generated customer invoice PDF (Admin → Orders → Invoice). Company details come from General & Brand → Company & Legal.')
                    ->schema([
                        Forms\Components\TextInput::make('payment_terms_days')
                            ->label('Payment Due (Days After Invoice Date)')
                            ->numeric()->minValue(0)->maxValue(365)->required()
                            ->helperText('Shown on the invoice as the "Due" date, calculated from the order date')
                            ->default(30),

                        AdminUi::translatableTabs('Thank You Text', [
                            'thank_you_text' => [
                                'label' => 'Thank You Text',
                                'type' => 'textarea',
                                'rows' => 2,
                                'placeholders' => [
                                    'en' => 'Thank you for your business!',
                                    'de' => 'Vielen Dank für Ihr Geschäft!',
                                    'lt' => 'Dėkojame už jūsų verslą!',
                                    'fr' => 'Merci pour votre confiance !',
                                    'es' => '¡Gracias por su compra!',
                                ],
                            ],
                        ]),
                    ])->columns(2),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Multi-group overrides — copied verbatim from SeoControlCenter.php /
    // SecurityAccessSettings.php. See class docblock.
    // ─────────────────────────────────────────────────────────────────────

    protected function fillForm(): void
    {
        $settings = Setting::whereIn('group', static::$settingsGroups)->get(['key', 'value', 'is_encrypted']);

        $data = [];
        foreach ($settings as $setting) {
            $value = $setting->value;

            if ($setting->is_encrypted && $value) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    Log::warning("Failed to decrypt setting {$setting->key}: " . $e->getMessage());
                }
            }

            if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                }
            }

            $data[$setting->key] = $value;
        }

        $this->form->fill($data);
    }

    public function save(): void
    {
        $this->validate();

        $oldValues = Setting::whereIn('group', static::$settingsGroups)->pluck('value', 'key')->toArray();
        $changed = $this->buildChangesDiff($oldValues);

        if (empty($changed)) {
            Notification::make()->title('No changes detected')->info()->send();

            return;
        }

        $this->persistChanges($oldValues);

        Notification::make()
            ->title('Settings saved')
            ->body('Cache cleared for: ' . implode(', ', static::$settingsGroups))
            ->success()
            ->send();
    }

    /** Maps a flat form field name to the settings group it actually belongs to. */
    private function groupForKey(string $key): string
    {
        static $map = null;

        if ($map === null) {
            $map = Setting::whereIn('group', static::$settingsGroups)->pluck('group', 'key')->all();
        }

        return $map[$key] ?? static::$settingsGroups[0];
    }

    private function persistChanges(array $oldValues): void
    {
        $service = app(SettingsService::class);
        $admin = auth('admin')->user();

        foreach ($this->data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            if (is_array($value)) {
                $value = empty($value) ? '' : json_encode($value);
            }

            $service->set($this->groupForKey($key) . '.' . $key, $value);

            if (array_key_exists($key, $oldValues) && (string) ($oldValues[$key] ?? '') !== (string) $value) {
                $oldValues[$key] = '***';
            }
        }

        if ($admin) {
            $encryptedKeys = $this->getEncryptedKeys();
            ActivityLog::create([
                'admin_id' => $admin->id,
                'action' => $this->resetMode ? 'settings_reset' : 'settings_updated',
                'model_type' => Setting::class,
                'model_id' => null,
                'old_values' => array_intersect_key($oldValues, $this->data),
                'new_values' => collect($this->data)
                    ->mapWithKeys(fn ($v, $k) => [$k => in_array($k, $encryptedKeys) && $v ? '***' : $v])
                    ->toArray(),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        $this->resetMode = false;
        $this->pendingChanges = null;
        $this->rememberData();

        $this->afterSave();
    }

    public function resetToDefaults(): void
    {
        $defaults = $this->getFactoryDefaults();

        if (empty($defaults)) {
            Notification::make()->title('No factory defaults defined')->warning()->send();

            return;
        }

        $oldValues = Setting::whereIn('group', static::$settingsGroups)->pluck('value', 'key')->toArray();
        $changed = $this->buildDiffBetween($oldValues, $defaults);

        if (empty($changed)) {
            Notification::make()->title('Settings already at defaults')->info()->send();

            return;
        }

        $this->pendingChanges = [
            'oldValues' => $oldValues,
            'changed' => $changed,
            'resetDefaults' => $defaults,
        ];
        $this->resetMode = true;
    }

    protected function getFactoryDefaults(): array
    {
        return collect(SettingsSeeder::definitions())
            ->whereIn('group', static::$settingsGroups)
            ->mapWithKeys(fn (array $row) => [$row['key'] => $this->castDefinitionValue($row['value'], $row['type'])])
            ->all();
    }

    private static function castDefinitionValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            SettingType::Boolean->value => in_array($value, ['1', 1, true], true),
            SettingType::Integer->value => (int) $value,
            SettingType::Decimal->value => (float) $value,
            SettingType::Json->value => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    protected function getEncryptedKeys(): array
    {
        return Setting::whereIn('group', static::$settingsGroups)
            ->where('is_encrypted', true)
            ->pluck('key')
            ->toArray();
    }
}
