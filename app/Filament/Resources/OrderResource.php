<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Support\AdminUi;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\SendTrackingUpdateEmail;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SequenceService;
use Filament\Forms;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                                        ->label('Order Number')
                                        ->required()
                                        ->maxLength(30)
                                        ->unique(ignoreRecord: true)
                                        ->disabled()
                                        ->dehydrated()
                                        ->helperText('Generated automatically when creating the order.'),
                                    Forms\Components\Select::make('user_id')
                                        ->relationship('user', 'name')
                                        ->label('Customer')
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->placeholder('Select a registered customer...')
                                        ->helperText('Registered customer placing this order. Leave empty for guest orders.'),
                                    Forms\Components\TextInput::make('guest_email')
                                        ->email()
                                        ->label('Guest Email')
                                        ->nullable()
                                        ->placeholder('e.g. customer@example.com')
                                        ->helperText('Fill this for guest checkout orders where no account exists.'),
                                ])
                                ->columns(2),
                            Section::make('Shipping Address')
                                ->icon('heroicon-o-map-pin')
                                ->description('Delivery recipient and destination details.')
                                ->schema([
                                    Forms\Components\TextInput::make('shipping_name')
                                        ->label('Recipient Name')
                                        ->required()
                                        ->maxLength(200)
                                        ->placeholder('e.g. John Doe')
                                        ->helperText('Full name of the person receiving the shipment.'),
                                    Forms\Components\TextInput::make('shipping_address_line1')
                                        ->label('Street Address')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g. Musterstraße 42')
                                        ->helperText('Primary street address including house number.'),
                                    Forms\Components\TextInput::make('shipping_city')
                                        ->label('City')
                                        ->required()
                                        ->maxLength(100)
                                        ->placeholder('e.g. Berlin'),
                                    Forms\Components\TextInput::make('shipping_postal_code')
                                        ->label('Postal Code')
                                        ->required()
                                        ->maxLength(20)
                                        ->placeholder('e.g. 10115'),
                                    Forms\Components\Select::make('shipping_country_code')
                                        ->label('Country')
                                        ->required()
                                        ->options(config('countries'))
                                        ->searchable()
                                        ->native(false)
                                        ->placeholder('Select country...'),
                                ])
                                ->columns(2),
                            Section::make('Additional')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->description('Notes, tracking information, and follow-up details.')
                                ->schema([
                                    Forms\Components\Textarea::make('customer_note')
                                        ->label('Customer Note')
                                        ->placeholder('Any special requests or instructions from the customer...')
                                        ->helperText('Optional message or special instructions provided by the customer at checkout.')
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('tracking_number')
                                        ->label('Tracking Number')
                                        ->nullable()
                                        ->maxLength(100)
                                        ->placeholder('e.g. DHL-1234567890')
                                        ->helperText('Carrier tracking reference for the shipment.'),
                                    Forms\Components\TextInput::make('carrier')
                                        ->label('Shipping Carrier')
                                        ->nullable()
                                        ->maxLength(100)
                                        ->placeholder('e.g. DHL, GLS, DPD')
                                        ->helperText('Name of the logistics provider handling delivery.'),
                                    Forms\Components\Toggle::make('urgent_processing')
                                        ->label('Urgent Processing')
                                        ->helperText('When enabled, this order is prioritized for same-day dispatch.')
                                        ->extraAttributes(['class' => 'op-urgent-toggle']),
                                    Forms\Components\TextInput::make('invoice_number')
                                        ->label('Invoice Number')
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
                                        ->label('Coupon')
                                        ->relationship('coupon', 'code')
                                        ->searchable()
                                        ->preload()
                                        ->nullable()
                                        ->helperText('Select the coupon applied to this order, if any.'),
                                    Forms\Components\Select::make('shipping_method_id')
                                        ->label('Shipping Method')
                                        ->relationship('shippingMethod', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->helperText('The delivery method chosen by the customer.'),
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
                                        ->label('Order Status')
                                        ->options(OrderStatus::class)
                                        ->required()
                                        ->default(OrderStatus::Pending)
                                        ->helperText('Determines the order stage in the fulfillment pipeline.'),
                                ]),
                            Section::make('Payment')
                                ->icon('heroicon-o-credit-card')
                                ->description('Payment method and transaction status.')
                                ->schema([
                                    Forms\Components\Select::make('payment_method')
                                        ->label('Payment Method')
                                        ->options(PaymentMethod::class)
                                        ->required()
                                        ->helperText('How the customer paid for this order.'),
                                    Forms\Components\Select::make('payment_status')
                                        ->label('Payment Status')
                                        ->options(PaymentStatus::class)
                                        ->required()
                                        ->default(PaymentStatus::Pending)
                                        ->helperText('Current state of the payment transaction.'),
                                    Forms\Components\TextInput::make('payment_reference')
                                        ->label('Payment Reference')
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
                                    Forms\Components\TextInput::make('subtotal')
                                        ->label('Subtotal')
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Forms\Components\TextInput::make('discount_amount')
                                        ->label('Discount (−)')
                                        ->numeric()
                                        ->prefix('€')
                                        ->default(0)
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Forms\Components\TextInput::make('shipping_cost')
                                        ->label('Shipping (+)')
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    Forms\Components\TextInput::make('vat_amount')
                                        ->label('VAT (+)')
                                        ->numeric()
                                        ->prefix('€')
                                        ->required()
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->extraAttributes(['class' => 'op-fin-input']),
                                    \Filament\Forms\Components\Placeholder::make('fin_divider')
                                        ->hiddenLabel()
                                        ->extraAttributes(['class' => 'op-fin-form-divider']),
                                    Forms\Components\TextInput::make('grand_total')
                                        ->label('Grand Total')
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
                                        ->label('B2B Order')
                                        ->helperText('Enable if this is a business-to-business transaction.'),
                                    Forms\Components\TextInput::make('company_name')
                                        ->label('Company Name')
                                        ->nullable()
                                        ->maxLength(200)
                                        ->placeholder('e.g. AutoParts GmbH')
                                        ->helperText('Legal company name for B2B invoicing.'),
                                    Forms\Components\TextInput::make('vat_number')
                                        ->label('VAT Number')
                                        ->nullable()
                                        ->maxLength(50)
                                        ->placeholder('e.g. DE123456789')
                                        ->helperText('EU VAT registration number for reverse-charge transactions.'),
                                    Forms\Components\Toggle::make('vat_exempt')
                                        ->label('VAT Exempt')
                                        ->helperText('Enable if the B2B customer is exempt from VAT (reverse-charge).'),
                                ])
                                ->columns(1),
                            Section::make('Technical Details')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->collapsed()
                                ->description('System-recorded metadata captured at time of order.')
                                ->schema([
                                    Forms\Components\TextInput::make('ip_address')
                                        ->label('IP Address')
                                        ->readOnly()
                                        ->helperText('Customer IP address captured at time of order placement.'),
                                    Forms\Components\TextInput::make('urgent_processing_fee')
                                        ->label('Urgent Processing Fee')
                                        ->numeric()
                                        ->prefix('€')
                                        ->minValue(0)
                                        ->step(0.01)
                                        ->readOnly()
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
                ->label('Customer')
                ->getStateUsing(fn (Order $record): string => $record->shipping_name ?? $record->user?->name ?? $record->guest_email ?? '—')
                ->description(fn (Order $record): ?string =>
                    $record->user?->email ?? ($record->guest_email ?: null)
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
                ->label('Status')
                ->badge()
                ->icon(fn (OrderStatus $state): string => match ($state) {
                    OrderStatus::Pending => 'heroicon-o-clock',
                    OrderStatus::Paid => 'heroicon-o-check-circle',
                    OrderStatus::Processing => 'heroicon-o-arrow-path',
                    OrderStatus::Shipped => 'heroicon-o-truck',
                    OrderStatus::Delivered => 'heroicon-o-check-badge',
                    OrderStatus::Cancelled => 'heroicon-o-x-circle',
                    OrderStatus::RefundRequested => 'heroicon-o-arrow-uturn-left',
                    OrderStatus::Refunded => 'heroicon-o-arrow-uturn',
                })
                ->color(fn (OrderStatus $state): string => AdminUi::orderStatusColor($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('payment_status')
                ->label('Payment')
                ->badge()
                ->icon(fn (PaymentStatus $state): string => match ($state) {
                    PaymentStatus::Pending => 'heroicon-o-clock',
                    PaymentStatus::Paid => 'heroicon-o-check-circle',
                    PaymentStatus::Failed => 'heroicon-o-x-circle',
                    PaymentStatus::Refunded => 'heroicon-o-arrow-uturn',
                })
                ->color(fn (PaymentStatus $state): string => AdminUi::paymentStatusColor($state))
                ->sortable(),
            Tables\Columns\TextColumn::make('items_count')
                ->label('Items')
                ->counts('items')
                ->fontMono()
                ->alignCenter(),
            Tables\Columns\TextColumn::make('grand_total')
                ->label('Total')
                ->getStateUsing(fn (Order $record): string => format_money($record->grand_total))
                ->description(fn (Order $record): string => $record->vat_amount > 0 ? 'incl. VAT' : 'excl. VAT')
                ->alignEnd()
                ->weight('bold')
                ->fontMono()
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Date')
                ->dateTime('d M Y H:i')
                ->sortable(),
            Tables\Columns\IconColumn::make('urgent_processing')
                ->label('Urgent')
                ->boolean()
                ->color('danger')
                ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Order Status')
                    ->options(OrderStatus::class)
                    ->multiple()
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options(PaymentStatus::class)
                    ->multiple()
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options(PaymentMethod::class)
                    ->native(false)
                    ->columnSpan(1),
                Tables\Filters\Filter::make('created_at')
                    ->label('Order Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('From Date'),
                        Forms\Components\DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->columnSpan(1),
                Tables\Filters\Filter::make('country')
                    ->label('Shipping Country')
                    ->form([
                        Forms\Components\TextInput::make('country_code')
                            ->label('Country Code')
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
                    ->label('B2B Only')
                    ->nullable()
                    ->columnSpan(1),
                Tables\Filters\TernaryFilter::make('urgent_processing')
                    ->label('Urgent Only')
                    ->nullable()
                    ->columnSpan(1),
            ])
            ->actions([
                ...AdminUi::recordActions([
                    static::makeChangeStatusAction(),
                    Actions\Action::make('printInvoice')
                        ->label('Print Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->authorize('update')
                        ->action(function (Order $record): void {
                            if (! $record->invoice_number) {
                                $record->invoice_number = app(SequenceService::class)->nextInvoiceNumber();
                                $record->save();
                            }
                            GenerateInvoicePdf::dispatch($record);
                            Notification::make()
                                ->title('Invoice queued')
                                ->body("Invoice {$record->invoice_number} is being generated.")
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered])),
                    Actions\Action::make('sendTracking')
                        ->label('Send Tracking')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Send Tracking Email')
                        ->modalDescription('Send the tracking number and carrier information to the customer via email.')
                        ->schema([
                            Forms\Components\TextInput::make('tracking_number')
                                ->label('Tracking Number')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('e.g. DHL-1234567890')
                                ->default(fn (Order $record): ?string => $record->tracking_number)
                                ->helperText('The carrier tracking reference for this shipment.'),
                            Forms\Components\TextInput::make('carrier')
                                ->label('Shipping Carrier')
                                ->maxLength(100)
                                ->placeholder('e.g. DHL, GLS, DPD')
                                ->default(fn (Order $record): ?string => $record->carrier)
                                ->helperText('Name of the logistics provider.'),
                        ])
                        ->action(function (Order $record, array $data): void {
                            $record->tracking_number = $data['tracking_number'];
                            $record->carrier = $data['carrier'] ?? $record->carrier;
                            $record->save();

                            dispatch(new SendTrackingUpdateEmail($record));

                            Notification::make()
                                ->title('Tracking email queued')
                                ->body("Tracking number: {$data['tracking_number']}")
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Processing, OrderStatus::Shipped])),
                    Actions\Action::make('confirmPayment')
                        ->label('Confirm Payment')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->authorize('update')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Bank Transfer Payment')
                        ->modalDescription('Mark this order as paid after verifying the bank transfer has been received in your account.')
                        ->schema([
                            Forms\Components\TextInput::make('transaction_id')
                                ->label('Transaction Reference')
                                ->maxLength(200)
                                ->placeholder('e.g. Bank reference, SWIFT code, or transaction ID')
                                ->helperText('Enter the payment reference from your bank statement for reconciliation.'),
                        ])
                        ->action(function (Order $record, array $data): void {
                            $payment = \App\Models\Payment::firstOrCreate(
                                ['order_id' => $record->id],
                                [
                                    'gateway'        => PaymentGateway::BankTransfer,
                                    'transaction_id' => $data['transaction_id'] ?? null,
                                    'status'         => PaymentTransactionStatus::Pending,
                                    'amount'         => $record->grand_total,
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
                        ->visible(fn (Order $record): bool =>
                            $record->payment_method === PaymentMethod::BankTransfer
                            && $record->payment_status === PaymentStatus::Pending
                        ),
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
                                    ->body('Failed: ' . implode(', ', $failed))
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
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (Order $record): ?string => $record->urgent_processing ? 'op-order-row-urgent' : null)
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders from the storefront will appear here once customers start purchasing.')
            ->emptyStateActions([
                Actions\Action::make('create')
                    ->label('Create Order')
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\OrderNotesRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\OrderStatusHistoryRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\PaymentRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\RefundRequestRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view'   => Pages\ViewOrder::route('/{record}'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
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
        return \App\Support\NavBadge::count('orders_pending', fn () => static::getModel()::where('status', OrderStatus::Pending)->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return (int) \App\Support\NavBadge::count('orders_pending', fn () => static::getModel()::where('status', OrderStatus::Pending)->count()) > 10 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Orders awaiting processing';
    }

    public static function makeChangeStatusAction(): Actions\Action
    {
        return Actions\Action::make('changeStatus')
            ->label('Change Status')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->authorize('update')
            ->modalHeading('Update Order Status')
            ->modalDescription('Change the current status of this order. A status history record will be created automatically.')
            ->schema([
                Forms\Components\Select::make('new_status')
                    ->label('New Status')
                    ->options(OrderStatus::class)
                    ->required()
                    ->helperText('Select the next stage in the order lifecycle.'),
                Forms\Components\Textarea::make('note')
                    ->label('Status Note')
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

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $status = $record->status;

        return [
            'Status' => \Illuminate\Support\Str::headline($status instanceof \BackedEnum ? $status->value : (string) $status),
            'Total' => format_money($record->grand_total),
        ];
    }
}
