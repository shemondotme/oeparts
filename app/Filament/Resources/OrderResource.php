<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\OrderNotesRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\OrderStatusHistoryRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\RefundRequestRelationManager;
use App\Filament\Support\AdminUi;
use App\Jobs\SendTrackingUpdateEmail;
use App\Models\Carrier;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\AdminOrderCalculator;
use App\Services\OemNormalizerService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SequenceService;
use App\Support\NavBadge;
use Filament\Actions;
use Filament\Actions\Action as NotificationAction;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'order_number';
    }

    /**
     * Recompute the totals of an order being created from its items, destination,
     * shipping method, coupon and VAT status. $prefix is the path back to the form
     * root when called from inside a repeater row ('../../').
     */
    public static function recalculate(Get $get, Set $set, string $prefix = ''): void
    {
        if ($get($prefix.'manual_totals')) {
            return;
        }

        $items = collect($get($prefix.'line_items') ?? [])
            ->map(fn (array $row): array => ['quantity' => $row['quantity'] ?? 0, 'unit_price' => $row['unit_price'] ?? 0])
            ->all();

        $r = app(AdminOrderCalculator::class)->compute(
            $items,
            $get($prefix.'shipping_country_code') ?: null,
            $get($prefix.'shipping_method_id') ? (int) $get($prefix.'shipping_method_id') : null,
            $get($prefix.'coupon_id') ? (int) $get($prefix.'coupon_id') : null,
            $get($prefix.'user_id') ? (int) $get($prefix.'user_id') : null,
            $get($prefix.'guest_email') ?: null,
            (bool) $get($prefix.'vat_exempt'),
        );

        foreach (['subtotal', 'discount_amount', 'shipping_cost', 'vat_amount', 'grand_total'] as $field) {
            $set($prefix.$field, $r[$field]);
        }

        $note = 'VAT '.rtrim(rtrim($r['vat_rate'], '0'), '.').'% — '.$r['vat_reason'].'.';
        if ($r['warnings'] !== []) {
            $note .= ' Warning: '.implode(' ', $r['warnings']);
        }
        $set($prefix.'calc_note', $note);
    }

    /** Copy a registered customer's saved details into the order form. */
    public static function fillFromCustomer(mixed $userId, Get $get, Set $set): void
    {
        if (! $userId || ! ($user = User::find($userId))) {
            return;
        }

        /** @var UserAddress|null $address */
        $address = $user->addresses()->orderByDesc('is_default')->orderBy('id')->first();

        $set('customer_phone', $address?->phone ?: $user->phone);
        $set('shipping_name', ($address ? trim($address->first_name.' '.$address->last_name) : '') ?: $user->name);

        if ($address) {
            $set('shipping_address_line1', $address->address_line1);
            $set('shipping_address_line2', $address->address_line2);
            $set('shipping_city', $address->city);
            $set('shipping_state', $address->state);
            $set('shipping_postal_code', $address->postal_code);
            $set('shipping_country_code', $address->country_code);
            if (filled($address->company)) {
                $set('company_name', $address->company);
            }
        }

        self::recalculate($get, $set);
    }

    /** @return array<int|string, string> product id => label, indexed lookups only (the catalog has 1M+ rows). */
    public static function searchProducts(string $search): array
    {
        $term = app(OemNormalizerService::class)->normalize($search);
        if ($term === '') {
            return [];
        }

        return Product::query()
            ->with(['manufacturer', 'condition'])
            ->where('normalized_oem', 'like', $term.'%')
            ->orderByDesc('is_in_stock')
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Product $p): array => [$p->id => self::productLabel($p)])
            ->all();
    }

    public static function productLabel(?Product $p): ?string
    {
        if (! $p) {
            return null;
        }

        /** @var Manufacturer|null $manufacturer */
        $manufacturer = $p->manufacturer;
        /** @var Condition|null $condition */
        $condition = $p->condition;

        return implode(' — ', array_filter([
            $p->oem_number,
            $manufacturer ? AdminUi::localizedName($manufacturer->name) : null,
            $condition ? $condition->slug : null,
            '€'.number_format((float) $p->price, 2),
            $p->is_in_stock ? null : 'OUT OF STOCK',
        ]));
    }

    /** Blank the billing_* columns unless the order really bills elsewhere, so "same as shipping" stays null. */
    public static function normalizeBilling(array $data): array
    {
        if (! ($data['billing_different'] ?? filled($data['billing_address_line1'] ?? null))) {
            foreach (['billing_name', 'billing_address_line1', 'billing_address_line2', 'billing_city', 'billing_state', 'billing_postal_code', 'billing_country_code'] as $key) {
                $data[$key] = null;
            }
        }
        unset($data['billing_different']);

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make([
                            Section::make('Customer')
                                ->icon('heroicon-o-user')
                                ->description('Customer identity and order number details.')
                                ->schema([
                                    Forms\Components\TextInput::make('order_number')
                                        ->label(__('admin.order_number'))
                                        ->maxLength(30)
                                        ->unique(ignoreRecord: true)
                                        ->disabled()
                                        ->dehydrated()
                                        ->helperText('Generated automatically when creating the order.'),
                                    Forms\Components\Select::make('user_id')
                                        ->relationship('user', 'name')
                                        ->label(__('admin.customer'))
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->placeholder('Select a registered customer...')
                                        ->live()
                                        ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::fillFromCustomer($state, $get, $set))
                                        ->helperText('Registered customer placing this order. Leave empty for guest orders. Choosing one fills in their saved name, phone and address.'),
                                    Forms\Components\TextInput::make('guest_email')
                                        ->email()
                                        ->label(__('admin.guest_email'))
                                        ->nullable()
                                        ->placeholder('e.g. customer@example.com')
                                        ->helperText('Fill this for guest checkout orders where no account exists.'),
                                    Forms\Components\TextInput::make('customer_phone')
                                        ->label('Customer phone')
                                        ->tel()
                                        ->nullable()
                                        ->maxLength(50)
                                        ->placeholder('e.g. +370 600 00000')
                                        ->helperText('For the courier and for support follow-up.'),
                                ])
                                ->columns(2),
                            Section::make('Items')
                                ->icon('heroicon-o-cube')
                                ->description('Search the catalog by OEM number. Prices fill in from the catalog and can be changed per line; totals below update automatically.')
                                ->visibleOn('create')
                                ->schema([
                                    Forms\Components\Repeater::make('line_items')
                                        ->hiddenLabel()
                                        ->addActionLabel('Add part')
                                        ->minItems(fn (Get $get): int => $get('manual_totals') ? 0 : 1)
                                        ->defaultItems(1)
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set))
                                        ->columns(12)
                                        ->schema([
                                            Forms\Components\Select::make('product_id')
                                                ->label('Part (OEM number)')
                                                ->required(fn (Get $get): bool => ! $get('../../manual_totals'))
                                                ->searchable()
                                                ->native(false)
                                                ->live()
                                                ->columnSpan(5)
                                                ->getSearchResultsUsing(fn (string $search): array => self::searchProducts($search))
                                                ->getOptionLabelUsing(fn ($value): ?string => self::productLabel(Product::with(['manufacturer', 'condition'])->find($value)))
                                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                                    $price = $state ? Product::whereKey($state)->value('price') : null;
                                                    $set('unit_price', $price !== null ? number_format((float) $price, 2, '.', '') : null);
                                                    self::recalculate($get, $set, '../../');
                                                }),
                                            Forms\Components\TextInput::make('quantity')
                                                ->label('Qty')
                                                ->numeric()
                                                ->integer()
                                                ->minValue(1)
                                                ->default(1)
                                                ->required()
                                                ->live(onBlur: true)
                                                ->columnSpan(2)
                                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set, '../../')),
                                            Forms\Components\TextInput::make('unit_price')
                                                ->label('Unit price')
                                                ->numeric()
                                                ->prefix('€')
                                                ->minValue(0)
                                                ->step(0.01)
                                                ->required(fn (Get $get): bool => ! $get('../../manual_totals'))
                                                ->live(onBlur: true)
                                                ->columnSpan(3)
                                                ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set, '../../')),
                                            Forms\Components\TextInput::make('cost_price')
                                                ->label('Cost (internal)')
                                                ->numeric()
                                                ->prefix('€')
                                                ->minValue(0)
                                                ->step(0.01)
                                                ->columnSpan(2),
                                        ]),
                                ]),
                            Section::make('Shipping Address')
                                ->icon('heroicon-o-map-pin')
                                ->description('Delivery recipient and destination details.')
                                ->schema([
                                    Forms\Components\TextInput::make('shipping_name')
                                        ->label(__('admin.recipient_name'))
                                        ->required()
                                        ->maxLength(200)
                                        ->placeholder('e.g. John Doe')
                                        ->helperText('Full name of the person receiving the shipment.'),
                                    Forms\Components\TextInput::make('shipping_address_line1')
                                        ->label(__('admin.street_address'))
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g. Musterstraße 42')
                                        ->helperText('Primary street address including house number.'),
                                    Forms\Components\TextInput::make('shipping_address_line2')
                                        ->label('Address line 2')
                                        ->nullable()
                                        ->maxLength(255)
                                        ->placeholder('Apartment, suite, unit …')
                                        ->helperText('Optional.'),
                                    Forms\Components\TextInput::make('shipping_city')
                                        ->label(__('admin.city'))
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Berlin'),
                                    Forms\Components\TextInput::make('shipping_state')
                                        ->label('State / region')
                                        ->nullable()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Kanagawa')
                                        ->helperText('Needed for destinations such as Japan or the US.'),
                                    Forms\Components\TextInput::make('shipping_postal_code')
                                        ->label(__('admin.postal_code'))
                                        ->required()
                                        ->maxLength(20)
                                        ->placeholder('e.g. 10115'),
                                    Forms\Components\Select::make('shipping_country_code')
                                        ->label(__('admin.country'))
                                        ->required()
                                        ->options(config('countries'))
                                        ->searchable()
                                        ->native(false)
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set))
                                        ->placeholder('Select country...'),
                                ])
                                ->columns(2),
                            Section::make('Billing Address')
                                ->icon('heroicon-o-document-text')
                                ->description('Only if the invoice goes to a different address than the delivery (e.g. head office vs. workshop).')
                                ->collapsed()
                                ->schema([
                                    Forms\Components\Toggle::make('billing_different')
                                        ->label('Bill to a different address')
                                        ->live()
                                        ->dehydrated(true)
                                        ->default(false)
                                        ->afterStateHydrated(function (Forms\Components\Toggle $component, ?Order $record): void {
                                            $component->state(filled($record?->billing_address_line1));
                                        })
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('billing_name')
                                        ->label('Name / company on invoice')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                    Forms\Components\TextInput::make('billing_address_line1')
                                        ->label('Address line 1')
                                        ->maxLength(255)
                                        ->required(fn (Get $get): bool => (bool) $get('billing_different'))
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                    Forms\Components\TextInput::make('billing_address_line2')
                                        ->label('Address line 2')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                    Forms\Components\TextInput::make('billing_city')
                                        ->label('City')
                                        ->maxLength(100)
                                        ->required(fn (Get $get): bool => (bool) $get('billing_different'))
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                    Forms\Components\TextInput::make('billing_state')
                                        ->label('State / region')
                                        ->maxLength(100)
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                    Forms\Components\TextInput::make('billing_postal_code')
                                        ->label('Postal code')
                                        ->maxLength(20)
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                    Forms\Components\Select::make('billing_country_code')
                                        ->label('Country')
                                        ->options(config('countries'))
                                        ->searchable()
                                        ->native(false)
                                        ->required(fn (Get $get): bool => (bool) $get('billing_different'))
                                        ->visible(fn (Get $get): bool => (bool) $get('billing_different')),
                                ])
                                ->columns(2),
                            Section::make('Additional')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->description('Notes, tracking information, and follow-up details.')
                                ->schema([
                                    Forms\Components\Textarea::make('customer_note')
                                        ->label(__('admin.customer_note'))
                                        ->placeholder('Any special requests or instructions from the customer...')
                                        ->helperText('Optional message or special instructions provided by the customer at checkout.')
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('tracking_number')
                                        ->label(__('admin.tracking_number'))
                                        ->nullable()
                                        ->maxLength(100)
                                        ->placeholder('e.g. DHL-1234567890')
                                        ->helperText('Carrier tracking reference for the shipment.'),
                                    Forms\Components\Select::make('carrier_id')
                                        ->label(__('admin.shipping_carrier'))
                                        ->options(fn (): array => Carrier::query()
                                            ->where('is_active', true)
                                            ->orderBy('sort_order')
                                            ->pluck('name', 'id')
                                            ->all())
                                        ->searchable()
                                        ->nullable()
                                        ->native(false)
                                        ->placeholder('Select carrier...')
                                        ->helperText('Carriers are managed under Commerce → Carriers; the tracking link in customer emails is built from the carrier\'s URL template.'),
                                    Forms\Components\Toggle::make('send_confirmation')
                                        ->label('Email the order confirmation to the customer')
                                        ->helperText('Off by default so a phone order is not emailed before you have checked it. Needs the customer email above.')
                                        ->visibleOn('create')
                                        ->dehydrated(true)
                                        ->default(false),
                                    Forms\Components\Toggle::make('urgent_processing')
                                        ->label(__('admin.urgent_processing'))
                                        ->helperText('When enabled, this order is prioritized for same-day dispatch.')
                                        ->extraAttributes(['class' => 'op-urgent-toggle']),
                                    Forms\Components\TextInput::make('invoice_number')
                                        ->label(__('admin.invoice_number'))
                                        ->nullable()
                                        ->maxLength(30)
                                        ->placeholder('e.g. INV-2024-001')
                                        ->helperText('Internal invoice reference linked to this order.'),
                                ])
                                ->columns(2),
                            Section::make('Discount & Shipping')
                                ->icon('heroicon-o-truck')
                                ->description('Applied coupon code and selected shipping method.')
                                ->schema([
                                    Forms\Components\Select::make('coupon_id')
                                        ->label(__('admin.coupon'))
                                        ->relationship('coupon', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set))
                                        ->helperText('Select the coupon applied to this order, if any. The discount is checked against the same rules as the storefront.'),
                                    Forms\Components\Select::make('shipping_method_id')
                                        ->label(__('admin.shipping_method'))
                                        ->relationship('shippingMethod', 'name')
                                        ->searchable()
                                        ->preload()
                                        // ShippingMethod.name is multilang JSON
                                        // (array cast) — without this override,
                                        // Filament's default option-label
                                        // resolution hands the raw array to
                                        // Select::getOptionLabel(), which
                                        // requires a ?string and throws
                                        // immediately, confirmed live. Same
                                        // fix pattern as ProductResource's
                                        // manufacturer_id select.
                                        ->getOptionLabelFromRecordUsing(fn ($record) => AdminUi::localizedName($record->name))
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set))
                                        ->helperText('The delivery method. Its price fills in automatically (free above the threshold of the method) and it must serve the destination country.'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 2]),
                        Group::make([
                            Section::make('Status')
                                ->icon('heroicon-o-arrow-path')
                                ->description('Current processing stage of this order.')
                                ->schema([
                                    Forms\Components\Select::make('status')
                                        ->label(__('admin.order_status'))
                                        ->options(OrderStatus::class)
                                        ->required()
                                        ->default(OrderStatus::Pending)
                                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                                        ->dehydrated(fn (string $operation): bool => $operation === 'create')
                                        ->helperText(fn (string $operation): string => $operation === 'edit'
                                            ? 'Status changes go through the "Change Status" action above — it validates the transition, records history, and notifies the customer.'
                                            : 'Determines the order stage in the fulfillment pipeline.'),
                                ]),
                            Section::make('Payment')
                                ->icon('heroicon-o-credit-card')
                                ->description('Payment method and transaction status.')
                                ->schema([
                                    Forms\Components\Select::make('payment_method')
                                        ->label(__('admin.payment_method'))
                                        ->options(PaymentMethod::class)
                                        ->required()
                                        ->helperText('How the customer paid for this order.'),
                                    Forms\Components\Select::make('payment_status')
                                        ->label(__('admin.payment_status'))
                                        ->options(PaymentStatus::class)
                                        ->required()
                                        ->default(PaymentStatus::Pending)
                                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                                        ->dehydrated(fn (string $operation): bool => $operation === 'create')
                                        ->helperText(fn (string $operation): string => $operation === 'edit'
                                            ? 'Payment status is managed by the payment flow ("Confirm Payment" for bank transfers, webhooks for card).'
                                            : 'Current state of the payment transaction.'),
                                    Forms\Components\TextInput::make('payment_reference')
                                        ->label(__('admin.payment_reference'))
                                        ->nullable()
                                        ->maxLength(100)
                                        ->placeholder('e.g. TXN-ABC123')
                                        ->helperText('Transaction ID or reference from the payment gateway.'),
                                ]),
                            Section::make('Financials')
                                ->icon('heroicon-o-banknotes')
                                ->description('Order line-item costs and totals in EUR.')
                                ->extraAttributes(['class' => 'op-financials-form'])
                                ->schema([
                                    Forms\Components\Toggle::make('manual_totals')
                                        ->label('Adjust totals manually')
                                        ->helperText('Off: subtotal, discount, shipping, VAT and total are calculated from the items. Turn on only to override them by hand (the values are then saved exactly as typed).')
                                        ->visibleOn('create')
                                        ->live()
                                        ->dehydrated(true)
                                        ->default(false)
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set)),
                                    Forms\Components\Hidden::make('calc_note')->dehydrated(false),
                                    Placeholder::make('calc_note_display')
                                        ->hiddenLabel()
                                        ->visibleOn('create')
                                        ->content(fn (Get $get): string => (string) ($get('calc_note') ?: 'Add items to calculate totals.'))
                                        ->extraAttributes(['class' => 'text-xs']),
                                    Forms\Components\TextInput::make('subtotal')
                                        ->label(__('admin.subtotal'))
                                        ->readOnly(fn (Get $get, string $operation): bool => $operation === 'create' && ! $get('manual_totals'))
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Forms\Components\TextInput::make('discount_amount')
                                        ->label(__('admin.discount'))
                                        ->readOnly(fn (Get $get, string $operation): bool => $operation === 'create' && ! $get('manual_totals'))
                                        ->numeric()
                                        ->prefix('€')
                                        ->default(0)
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Forms\Components\TextInput::make('shipping_cost')
                                        ->label(__('admin.shipping'))
                                        ->readOnly(fn (Get $get, string $operation): bool => $operation === 'create' && ! $get('manual_totals'))
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Forms\Components\TextInput::make('vat_amount')
                                        ->label(__('admin.vat'))
                                        ->readOnly(fn (Get $get, string $operation): bool => $operation === 'create' && ! $get('manual_totals'))
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Placeholder::make('fin_divider')
                                        ->hiddenLabel()
                                        ->extraAttributes(['class' => 'op-fin-form-divider']),
                                    Forms\Components\TextInput::make('grand_total')
                                        ->label(__('admin.grand_total'))
                                        ->readOnly(fn (Get $get, string $operation): bool => $operation === 'create' && ! $get('manual_totals'))
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-form-total']),
                                ]),
                            Section::make('B2B')
                                ->icon('heroicon-o-building-office')
                                ->collapsed()
                                ->description('Business-to-business invoice and tax exemption details.')
                                ->schema([
                                    Forms\Components\Toggle::make('is_b2b')
                                        ->label(__('admin.b2b_order'))
                                        ->helperText('Enable if this is a business-to-business transaction.'),
                                    Forms\Components\TextInput::make('company_name')
                                        ->label(__('admin.company_name'))
                                        ->nullable()
                                        ->maxLength(200)
                                        ->placeholder('e.g. AutoParts GmbH')
                                        ->helperText('Legal company name for B2B invoicing.'),
                                    Forms\Components\TextInput::make('vat_number')
                                        ->label(__('admin.vat_number'))
                                        ->nullable()
                                        ->maxLength(50)
                                        ->placeholder('e.g. DE123456789')
                                        ->helperText('EU VAT registration number for reverse-charge transactions.'),
                                    Forms\Components\Toggle::make('vat_exempt')
                                        ->label(__('admin.vat_exempt'))
                                        ->live()
                                        ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculate($get, $set))
                                        ->helperText('Enable if the B2B customer is exempt from VAT (reverse-charge).'),
                                ])
                                ->columns(1),
                            Section::make('Technical Details')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->collapsed()
                                ->description('System-recorded metadata captured at time of order.')
                                ->schema([
                                    Forms\Components\TextInput::make('ip_address')
                                        ->label(__('admin.ip_address'))
                                        ->readOnly()
                                        ->helperText('Customer IP address captured at time of order placement.'),
                                    Forms\Components\TextInput::make('urgent_processing_fee')
                                        ->label(__('admin.urgent_processing_fee'))
                                        ->numeric()
                                        ->prefix('€')
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->readOnly()
                                        // The column has a DB-level default
                                        // ('0.00', migration 2026_03_26_100047),
                                        // but that only applies when the column
                                        // is OMITTED from the INSERT — Filament
                                        // always submits every schema-declared
                                        // field, so an untouched (readOnly, no
                                        // form default) TextInput submitted an
                                        // explicit null that overrode the DB
                                        // default, throwing a raw SQLSTATE NOT
                                        // NULL failure on every manual order
                                        // create, confirmed live.
                                        ->default(0)
                                        ->helperText('Additional surcharge applied for urgent same-day dispatch.'),
                                ])
                                ->columns(2),
                        ])
                            ->columnSpan(['default' => 1, 'xl' => 1]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('user')->withCount('items'))
            ->columns([
                AdminUi::copyableColumn('order_number', 'Order #', 'Order number copied')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('admin.customer'))
                    ->getStateUsing(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                    ->description(fn (Order $record): ?string => $record->user?->email ?? ($record->guest_email ?: null)
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('shipping_name', 'like', "%{$search}%")
                                ->orWhere('guest_email', 'like', "%{$search}%")
                                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->icon(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'heroicon-o-clock',
                        OrderStatus::Paid => 'heroicon-o-check-circle',
                        OrderStatus::Processing => 'heroicon-o-arrow-path',
                        OrderStatus::Shipped => 'heroicon-o-truck',
                        OrderStatus::Delivered => 'heroicon-o-check-badge',
                        OrderStatus::Cancelled => 'heroicon-o-x-circle',
                        OrderStatus::RefundRequested => 'heroicon-o-arrow-uturn-left',
                        OrderStatus::Refunded => 'heroicon-o-receipt-refund',
                    })
                    ->color(fn (OrderStatus $state): string => AdminUi::orderStatusColor($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label(__('admin.payment'))
                    ->badge()
                    ->icon(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Pending => 'heroicon-o-clock',
                        PaymentStatus::Paid => 'heroicon-o-check-circle',
                        PaymentStatus::Failed => 'heroicon-o-x-circle',
                        PaymentStatus::Refunded => 'heroicon-o-receipt-refund',
                    })
                    ->color(fn (PaymentStatus $state): string => AdminUi::paymentStatusColor($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('admin.items'))
                    ->counts('items')
                    ->fontMono()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('grand_total')
                    ->label(__('admin.total'))
                    ->getStateUsing(fn (Order $record): string => format_money($record->grand_total))
                    ->description(fn (Order $record): string => $record->vat_amount > 0 ? 'incl. VAT' : 'excl. VAT')
                    ->alignEnd()
                    ->weight('bold')
                    ->fontMono()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\IconColumn::make('urgent_processing')
                    ->label(__('admin.urgent'))
                    // Icon only when urgent — a column of red X's for "normal" reads as alarm.
                    ->icon(fn (bool $state): ?string => $state ? 'heroicon-o-exclamation-triangle' : null)
                    ->color('danger')
                    ->tooltip(fn (bool $state): ?string => $state ? 'Urgent processing — same-day dispatch' : null)
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.order_status'))
                    ->options(OrderStatus::class)
                    ->multiple()
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label(__('admin.payment_status'))
                    ->options(PaymentStatus::class)
                    ->multiple()
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label(__('admin.payment_method'))
                    ->options(PaymentMethod::class)
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label(__('admin.order_date_range'))
                    ->form([
                        Forms\Components\DatePicker::make('from')->label(__('admin.from_date')),
                        Forms\Components\DatePicker::make('until')->label(__('admin.until_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->columnSpan(1),
                Tables\Filters\Filter::make('country')
                    ->label(__('admin.shipping_country'))
                    ->form([
                        Forms\Components\TextInput::make('country_code')
                            ->label(__('admin.country_code'))
                            ->maxLength(2)
                            ->placeholder('e.g. DE')
                            ->helperText('ISO 3166-1 alpha-2 code.'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['country_code'],
                            fn ($q, $code) => $q->where('shipping_country_code', strtoupper($code))
                        );
                    })
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('is_b2b')
                    ->label(__('admin.b2b_only'))
                    ->nullable()
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('urgent_processing')
                    ->label(__('admin.urgent_only'))
                    ->nullable()
                    ->columnSpan(1),
            ])
            ->actions([
                ...AdminUi::recordActions([
                    static::makeChangeStatusAction(),
                    NotificationAction::make('printInvoice')
                        ->label(__('admin.print_invoice'))
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->authorize('update')
                        // Was a fire-a-queue-job-and-notify action whose
                        // notification never actually linked anywhere — an
                        // admin had no way to reach the PDF it claimed to be
                        // generating short of navigating elsewhere and
                        // guessing. Now redirects straight to the same
                        // cache-aware download route customers use
                        // (InvoiceService::download()); its
                        // Content-Disposition: attachment means the browser
                        // downloads the file in place rather than actually
                        // navigating away from this list.
                        ->action(function (Order $record) {
                            if (! $record->invoice_number) {
                                $record->invoice_number = app(SequenceService::class)->nextInvoiceNumber();
                                $record->save();
                            }

                            return redirect()->to(route('admin.orders.invoice', ['order' => $record]));
                        })
                        ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered])),
                    NotificationAction::make('sendTracking')
                        ->label(__('admin.send_tracking'))
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Send Tracking Email')
                        ->modalDescription('Send the tracking number and carrier information to the customer via email.')
                        ->schema([
                            Forms\Components\TextInput::make('tracking_number')
                                ->label(__('admin.tracking_number'))
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. DHL-1234567890')
                                ->default(fn (Order $record): ?string => $record->tracking_number)
                                ->helperText('The carrier tracking reference for this shipment.'),
                            Forms\Components\Select::make('carrier_id')
                                ->label(__('admin.shipping_carrier'))
                                ->options(fn (): array => Carrier::query()
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->native(false)
                                ->default(fn (Order $record): ?int => $record->carrier_id)
                                ->helperText('The email\'s tracking link is built from this carrier\'s URL template.'),
                        ])
                        ->action(function (Order $record, array $data): void {
                            $record->tracking_number = $data['tracking_number'];
                            $record->carrier_id = $data['carrier_id'] ?? $record->carrier_id;
                            $record->save();

                            dispatch(new SendTrackingUpdateEmail($record));

                            Notification::make()
                                ->title('Tracking email queued')
                                ->body("Tracking number: {$data['tracking_number']}")
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Processing, OrderStatus::Shipped])),
                    NotificationAction::make('confirmPayment')
                        ->label(__('admin.confirm_payment'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Bank Transfer Payment')
                        ->modalDescription('Mark this order as paid after verifying the bank transfer has been received in your account.')
                        ->schema([
                            Forms\Components\TextInput::make('transaction_id')
                                ->label(__('admin.transaction_reference'))
                                ->maxLength(200)
                                ->placeholder('e.g. Bank reference, SWIFT code, or transaction ID')
                                ->helperText('Enter the payment reference from your bank statement for reconciliation.'),
                        ])
                        ->action(function (Order $record, array $data): void {
                            $payment = Payment::firstOrCreate(
                                ['order_id' => $record->id],
                                [
                                    'gateway' => PaymentGateway::BankTransfer,
                                    'transaction_id' => $data['transaction_id'] ?? null,
                                    'status' => PaymentTransactionStatus::Pending,
                                    'amount' => $record->grand_total,
                                ]
                            );

                            if (! empty($data['transaction_id']) && ! $payment->transaction_id) {
                                $payment->update(['transaction_id' => $data['transaction_id']]);
                            }

                            try {
                                app(PaymentService::class)->confirmBankTransferPayment(
                                    $payment,
                                    $data['transaction_id'] ?? '',
                                    auth('admin')->id(),
                                );

                                Notification::make()
                                    ->title('Payment confirmed')
                                    ->body("Order {$record->order_number} marked as paid.")
                                    ->success()
                                    ->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Confirmation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn (Order $record): bool => $record->payment_method === PaymentMethod::BankTransfer
                            && $record->payment_status === PaymentStatus::Pending
                        ),
                    NotificationAction::make('capturePayment')
                        ->label(__('admin.capture_payment'))
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Capture Held Payment')
                        ->modalDescription('Charge the customer\'s card now for the amount already authorized and held. This normally happens automatically when the order ships.')
                        ->action(function (Order $record): void {
                            $payment = $record->payments()
                                ->where('gateway', PaymentGateway::Airwallex)
                                ->where('status', PaymentTransactionStatus::Authorized)
                                ->latest()
                                ->first();

                            if (! $payment) {
                                Notification::make()
                                    ->title('No held payment found')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                app(PaymentService::class)->captureAirwallexPayment($payment);

                                Notification::make()
                                    ->title('Capture requested')
                                    ->body('Airwallex will confirm shortly; the payment record updates automatically.')
                                    ->success()
                                    ->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Capture failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->visible(fn (Order $record): bool => $record->payments()
                            ->where('gateway', PaymentGateway::Airwallex)
                            ->where('status', PaymentTransactionStatus::Authorized)
                            ->exists()),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::impactBulkAction(
                        name: 'markProcessing',
                        label: 'Mark as Processing',
                        color: 'warning',
                        icon: 'heroicon-o-arrow-path',
                        summary: fn ($record): ?array => $record->status !== OrderStatus::Paid
                            ? null
                            : [
                                'key' => $record->order_number,
                                'old' => $record->status->value,
                                'new' => OrderStatus::Processing->value,
                            ],
                        visible: fn ($records): bool => $records->contains(fn ($r) => $r->status === OrderStatus::Paid),
                        action: function ($records): void {
                            $service = app(OrderService::class);
                            $failed = [];

                            foreach ($records as $record) {
                                if ($record->status === OrderStatus::Paid) {
                                    try {
                                        $service->transitionStatus(
                                            $record,
                                            OrderStatus::Processing,
                                            'Bulk status update',
                                            auth('admin')->id(),
                                        );
                                    } catch (\InvalidArgumentException) {
                                        $failed[] = $record->order_number;
                                    }
                                }
                            }

                            if (empty($failed)) {
                                Notification::make()
                                    ->title('Orders marked as processing')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Some orders could not be updated')
                                    ->body('Failed: '.implode(', ', $failed))
                                    ->warning()
                                    ->send();
                            }
                        },
                    )->authorize('update'),
                    AdminUi::exportCsvBulkAction('Export Orders', [
                        'order_number' => 'Order Number',
                        'shipping_name' => 'Customer',
                        'status' => 'Status',
                        'payment_status' => 'Payment',
                        'grand_total' => 'Total',
                        'created_at' => 'Date',
                    ]),
                    // No bulk delete: cancellation via Change Status is the
                    // order lifecycle; single-record delete (soft) remains
                    // on the edit/view pages for test/junk orders.
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Order $record): ?string => $record->urgent_processing ? 'op-order-row-urgent' : null)
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders from the storefront will appear here once customers start purchasing.')
            ->emptyStateActions([
                NotificationAction::make('create')
                    ->label(__('admin.create_order'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
            OrderNotesRelationManager::class,
            OrderStatusHistoryRelationManager::class,
            PaymentRelationManager::class,
            RefundRequestRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $admin = auth('admin')->user();

        return $admin && ($admin->hasRole('super_admin') || $admin->hasPermissionTo('view orders'));
    }

    public static function canCreate(): bool
    {
        $admin = auth('admin')->user();

        return $admin && ($admin->hasRole('super_admin') || $admin->hasPermissionTo('edit orders'));
    }

    public static function canEdit($record): bool
    {
        $admin = auth('admin')->user();

        return $admin && ($admin->hasRole('super_admin') || $admin->hasPermissionTo('edit orders'));
    }

    public static function canDelete($record): bool
    {
        $admin = auth('admin')->user();

        return $admin && ($admin->hasRole('super_admin') || $admin->hasPermissionTo('edit orders'));
    }

    public static function getNavigationBadge(): ?string
    {
        return NavBadge::count('orders_pending', fn () => static::getModel()::where('status', OrderStatus::Pending)->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return (int) NavBadge::count('orders_pending', fn () => static::getModel()::where('status', OrderStatus::Pending)->count()) > 10 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Orders awaiting processing';
    }

    public static function makeChangeStatusAction(): NotificationAction
    {
        return NotificationAction::make('changeStatus')
            ->label(__('admin.change_status'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->authorize('update')
            ->modalHeading('Update Order Status')
            ->modalDescription('Change the current status of this order. A status history record will be created automatically.')
            ->schema([
                Forms\Components\Select::make('new_status')
                    ->label(__('admin.new_status'))
                    ->options(OrderStatus::class)
                    ->required()
                    ->helperText('Select the next stage in the order lifecycle.'),
                Forms\Components\Textarea::make('note')
                    ->label(__('admin.status_note'))
                    ->required()
                    ->rows(3)
                    ->placeholder('e.g. Payment verified, moving to processing...')
                    ->helperText('Internal note explaining why this status change was made.'),
            ])
            ->action(function (Order $record, array $data): void {
                try {
                    app(OrderService::class)->transitionStatus(
                        $record,
                        $data['new_status'],
                        $data['note'],
                        auth('admin')->id(),
                    );

                    Notification::make()
                        ->title('Order status updated')
                        ->success()
                        ->send();
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->title('Invalid status transition')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function getGloballySearchableAttributes(): array
    {
        // 'customer_name' is not a real column (orders has no such column) — it
        // threw "Unknown column orders.customer_name" on every order search.
        // The stored customer name lives in 'shipping_name'.
        return ['order_number', 'shipping_name', 'guest_email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $status = $record->status;

        return [
            'Status' => Str::headline($status instanceof \BackedEnum ? $status->value : (string) $status),
            'Total' => format_money($record->grand_total),
        ];
    }
}
