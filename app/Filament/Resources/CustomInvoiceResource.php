<?php

namespace App\Filament\Resources;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentMethod;
use App\Enums\InvoiceVatTreatment;
use App\Filament\Resources\CustomInvoiceResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\CustomInvoice;
use App\Models\InvoiceBankAccount;
use App\Models\InvoiceClient;
use App\Models\Product;
use App\Services\CustomInvoiceService;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class CustomInvoiceResource extends Resource
{
    protected static ?string $model = CustomInvoice::class;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static ?int $navigationSort = 15;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Sales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Quotes & Invoices';
    }

    public static function getModelLabel(): string
    {
        return 'document';
    }

    /**
     * Only drafts are editable: once an invoice has been sent it is a
     * numbered accounting document — cancel it and issue a new one instead.
     */
    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && $record->isEditable();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client')
                    ->icon('heroicon-o-user')
                    ->description('Who this document is addressed to. Pick a saved client to fill everything in.')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('Saved client')
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->placeholder('Type a company, name or email…')
                            ->getSearchResultsUsing(fn (string $search): array => InvoiceClient::query()
                                ->where(fn ($q) => $q->where('company', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                ->orderBy('company')
                                ->limit(25)
                                ->get()
                                ->mapWithKeys(fn (InvoiceClient $c): array => [$c->id => $c->displayName()])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => InvoiceClient::find($value)?->displayName())
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $client = $state ? InvoiceClient::find($state) : null;
                                if (! $client) {
                                    return;
                                }

                                foreach ($client->toInvoiceFields() as $field => $value) {
                                    if ($field !== 'client_id') {
                                        $set($field, $value);
                                    }
                                }
                                if (filled($client->currency)) {
                                    $set('currency', $client->currency);
                                }
                            })
                            ->helperText('Changing the fields below afterwards does not change the saved client.')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('save_client')
                            ->label('Save these details as a client for next time')
                            ->default(false)
                            ->dehydrated(true)
                            ->visible(fn (Get $get, string $operation): bool => $operation === 'create' && blank($get('client_id')))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('client_name')
                            ->label('Contact name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_company')
                            ->label('Company')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_vat_number')
                            ->label('Client VAT number')
                            ->maxLength(50)
                            ->suffixAction(InvoiceClientResource::vatCheckAction('client_vat_number', 'client_country_code')),
                        Forms\Components\TextInput::make('client_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Required to email the invoice to the client.'),
                        Forms\Components\TextInput::make('client_phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('client_address_line1')
                            ->label('Address line 1')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_address_line2')
                            ->label('Address line 2')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_city')
                            ->label('City')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_state')
                            ->label('State / region')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('client_postal_code')
                            ->label('Postal code')
                            ->maxLength(20),
                        Forms\Components\Select::make('client_country_code')
                            ->label('Country')
                            ->options(config('countries', []))
                            ->searchable()
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Document')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Forms\Components\Select::make('document_type')
                            ->label('Document type')
                            ->options(fn (string $operation): array => $operation === 'create'
                                ? [
                                    InvoiceDocumentType::Quote->value => InvoiceDocumentType::Quote->getLabel(),
                                    InvoiceDocumentType::Proforma->value => InvoiceDocumentType::Proforma->getLabel(),
                                    InvoiceDocumentType::Invoice->value => InvoiceDocumentType::Invoice->getLabel(),
                                ]
                                : collect(InvoiceDocumentType::cases())->mapWithKeys(fn (InvoiceDocumentType $c): array => [$c->value => $c->getLabel()])->all())
                            ->default(fn (): string => (InvoiceDocumentType::tryFrom((string) request()->query('type')) ?? InvoiceDocumentType::Invoice)->value)
                            ->native(false)
                            ->required()
                            ->live()
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated()
                            ->helperText('Quotation, proforma and invoice each have their own number series. A credit note is issued from an existing invoice.'),
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Issue date')
                            ->default(fn () => now())
                            ->required(),
                        Forms\Components\DatePicker::make('due_date')
                            ->label(fn (Get $get): string => self::documentTypeOf($get('document_type'))->dueLabel())
                            ->default(fn () => now()->addDays((int) settings('invoice.payment_terms_days', 30)))
                            ->afterOrEqual('issue_date')
                            ->required(),
                        Forms\Components\DatePicker::make('supply_date')
                            ->label('Date of supply')
                            ->helperText('Only if different from the issue date (goods shipped or service done on another day).'),
                        Forms\Components\Select::make('currency')
                            ->options(InvoiceBankAccountResource::currencies())
                            ->default(fn () => (string) settings('general.currency', 'EUR'))
                            ->native(false)
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('language')
                            ->label('Document language')
                            ->options(['en' => 'English', 'de' => 'Deutsch', 'es' => 'Español', 'fr' => 'Français', 'lt' => 'Lietuvių'])
                            ->default('en')
                            ->native(false)
                            ->required()
                            ->helperText('The language of the PDF and the email. Non-English wording is machine-translated: have it checked before relying on it. The VAT legal notice stays English unless you write your own.'),
                        Forms\Components\TextInput::make('po_number')
                            ->label("Client's PO / reference no.")
                            ->maxLength(100)
                            ->helperText("The client's own purchase-order or reference number, printed on the invoice."),
                        Forms\Components\TextInput::make('delivery_terms')
                            ->label('Delivery terms')
                            ->maxLength(150)
                            ->placeholder('e.g. DAP Tokyo, EXW Vilnius, DHL Express')
                            ->helperText('Incoterm or shipping method, printed on the invoice.'),
                    ])
                    ->columns(3),

                Section::make('Line items')
                    ->icon('heroicon-o-list-bullet')
                    ->description('Pick a part from the catalog to fill its number, description and price, or type a free line (shipping, handling, services).')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->hiddenLabel()
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add line')
                            ->live()
                            ->columns(['default' => 1, 'md' => 12])
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Catalog part (optional)')
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->columnSpan(['default' => 1, 'md' => 12])
                                    ->getSearchResultsUsing(fn (string $search): array => OrderResource::searchProducts($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => OrderResource::productLabel(Product::with(['manufacturer', 'condition'])->find($value)))
                                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                        $product = $state ? Product::find($state) : null;
                                        if (! $product) {
                                            return;
                                        }
                                        $set('part_number', $product->oem_number);
                                        if (blank($get('description'))) {
                                            $set('description', AdminUi::localizedName($product->name) ?: $product->oem_number);
                                        }
                                        $set('unit_price', number_format((float) $product->price, 2, '.', ''));
                                    }),
                                Forms\Components\TextInput::make('part_number')
                                    ->label('Part no.')
                                    ->maxLength(100)
                                    ->columnSpan(['default' => 1, 'md' => 3]),
                                Forms\Components\Textarea::make('description')
                                    ->required()
                                    ->rows(2)
                                    ->maxLength(1000)
                                    ->columnSpan(['default' => 1, 'md' => 9]),
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                Forms\Components\TextInput::make('unit')
                                    ->label('Unit')
                                    ->default('pcs')
                                    ->maxLength(20)
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 3]),
                                Forms\Components\TextInput::make('discount_percent')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                                Forms\Components\TextInput::make('lead_time')
                                    ->label('Availability / lead time')
                                    ->maxLength(100)
                                    ->placeholder('e.g. In stock, 5-7 days')
                                    ->columnSpan(['default' => 1, 'md' => 3]),
                                Forms\Components\TextInput::make('vat_rate')
                                    ->label('VAT % (line)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->placeholder('Invoice rate')
                                    ->live(onBlur: true)
                                    ->helperText('Leave empty to use the invoice rate.')
                                    ->columnSpan(['default' => 1, 'md' => 3]),
                            ]),
                    ]),

                Section::make('Tax, discount & notes')
                    ->icon('heroicon-o-receipt-percent')
                    ->schema([
                        Forms\Components\Select::make('vat_treatment')
                            ->label('VAT treatment')
                            ->options(InvoiceVatTreatment::class)
                            ->default(InvoiceVatTreatment::Standard)
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText('Anything but "Standard" means 0% VAT on every line and prints the legal wording below.'),
                        Forms\Components\TextInput::make('vat_rate')
                            ->label('Default VAT rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(fn () => (float) settings('tax.default_vat_rate', 0))
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => self::treatmentValue($get('vat_treatment')) === 'standard')
                            ->helperText('Applied to every line without its own VAT %.'),
                        Forms\Components\Select::make('discount_type')
                            ->label('Invoice discount')
                            ->options(['amount' => 'Fixed amount', 'percent' => 'Percentage of the subtotal'])
                            ->default('amount')
                            ->native(false)
                            ->live(),
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount (amount)')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => ($get('discount_type') ?: 'amount') === 'amount')
                            ->helperText('Taken off the subtotal before VAT.'),
                        Forms\Components\TextInput::make('discount_percent')
                            ->label('Discount (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->live(onBlur: true)
                            ->visible(fn (Get $get): bool => $get('discount_type') === 'percent')
                            ->helperText('Percentage of the subtotal, taken off before VAT.'),
                        Forms\Components\Textarea::make('vat_exemption_note')
                            ->label('Legal wording on the invoice')
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder(fn (Get $get): string => (string) (InvoiceVatTreatment::tryFrom(self::treatmentValue($get('vat_treatment')))?->defaultNotice() ?? ''))
                            ->visible(fn (Get $get): bool => self::treatmentValue($get('vat_treatment')) !== 'standard')
                            ->helperText('Leave empty to print the standard wording shown in grey. Confirm the wording with your accountant.')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('totals_preview')
                            ->label('Totals (calculated)')
                            ->content(fn (Get $get): string => self::totalsPreview($get))
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (printed on invoice)')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('terms_text')
                            ->label('Terms & conditions (printed)')
                            ->rows(3)
                            ->maxLength(4000)
                            ->helperText('e.g. retention of title, returns, late-payment interest.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal notes (never printed)')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Payment')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Get $get): bool => self::documentTypeOf($get('document_type'))->requestsPayment())
                    ->description('How the client should pay. This is printed on the PDF and in the email.')
                    ->schema([
                        Forms\Components\Select::make('payment_method')
                            ->label('Payment method')
                            ->options(InvoicePaymentMethod::class)
                            ->default(InvoicePaymentMethod::BankTransfer)
                            ->native(false)
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Bank account')
                            ->options(fn (): array => InvoiceBankAccount::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (InvoiceBankAccount $a): array => [$a->id => $a->label.' — '.$a->formattedIban()])
                                ->all())
                            ->placeholder('Automatic (account for this currency, else the default)')
                            ->native(false)
                            ->live()
                            ->visible(fn (Get $get): bool => self::methodValue($get('payment_method')) === 'bank_transfer')
                            ->helperText('Accounts are managed under Sales → Bank Accounts; the first active one is the default.'),
                        Forms\Components\Placeholder::make('bank_preview')
                            ->label('This invoice will print')
                            ->visible(fn (Get $get): bool => self::methodValue($get('payment_method')) === 'bank_transfer')
                            ->content(fn (Get $get): string => self::bankPreview($get('bank_account_id'), $get('currency')))
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('payment_link_url')
                            ->label('Online payment link')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://…')
                            ->helperText('A payment page you created at your payment provider for this invoice.')
                            ->visible(fn (Get $get): bool => self::methodValue($get('payment_method')) === 'payment_link')
                            ->required(fn (Get $get): bool => self::methodValue($get('payment_method')) === 'payment_link'),
                        Forms\Components\Textarea::make('payment_instructions')
                            ->label('Extra payment instructions')
                            ->rows(3)
                            ->maxLength(2000)
                            ->helperText('Optional free text printed under the payment details, e.g. "50% in advance, balance before shipping" or "Bank charges: OUR".')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    private static function documentTypeOf(mixed $state): InvoiceDocumentType
    {
        return $state instanceof InvoiceDocumentType
            ? $state
            : (InvoiceDocumentType::tryFrom((string) $state) ?? InvoiceDocumentType::Invoice);
    }

    private static function treatmentValue(mixed $state): string
    {
        return $state instanceof \BackedEnum ? (string) $state->value : (string) ($state ?: 'standard');
    }

    /** The totals as the saved invoice will have them, computed live from the form state. */
    public static function totalsPreview(Get $get): string
    {
        $type = (string) ($get('discount_type') ?: 'amount');

        $r = app(InvoiceCalculator::class)->calculate(
            (array) ($get('items') ?? []),
            self::treatmentValue($get('vat_treatment')),
            $get('vat_rate') ?? 0,
            $type,
            $type === 'percent' ? ($get('discount_percent') ?? 0) : ($get('discount_amount') ?? 0),
        );

        $currency = (string) ($get('currency') ?: 'EUR');
        $parts = ['Subtotal '.format_price($r['subtotal'], $currency, 'en')];

        if (bccomp($r['discount_amount'], '0', 2) > 0) {
            $parts[] = 'Discount -'.format_price($r['discount_amount'], $currency, 'en');
        }

        if ($r['treatment'] !== 'standard') {
            $parts[] = 'VAT '.format_price('0.00', $currency, 'en').' (none under this treatment)';
        } else {
            foreach ($r['breakdown'] as $row) {
                $parts[] = 'VAT '.rtrim(rtrim($row['rate'], '0'), '.').'% '.format_price($row['vat'], $currency, 'en');
            }
        }

        $parts[] = 'TOTAL '.format_price($r['total'], $currency, 'en');

        return implode('  ·  ', $parts);
    }

    /** Enum or raw string from the form state, as the plain method value. */
    private static function methodValue(mixed $state): string
    {
        return $state instanceof \BackedEnum ? (string) $state->value : (string) $state;
    }

    /** One-line summary of the bank account an invoice would print, with a clear warning when there is none. */
    public static function bankPreview(mixed $bankAccountId, mixed $currency): string
    {
        $currency = filled($currency) ? strtoupper((string) $currency) : null;
        $bank = app(InvoiceService::class)->bankDetailsFor($bankAccountId ? (int) $bankAccountId : null, $currency);

        if ($bank === null) {
            return 'Nothing — no bank account is set up. Add an account under Sales → Bank Accounts, otherwise the invoice shows no payment details.';
        }

        $line = trim($bank['account_holder'].' · '.$bank['iban'].($bank['bic'] !== '' ? ' · '.$bank['bic'] : '').($bank['bank_name'] !== '' ? ' · '.$bank['bank_name'] : ''), ' ·');

        $usesAccountTable = $bankAccountId
            || ($currency && InvoiceBankAccount::where('is_active', true)->where('currency', $currency)->exists());

        if (! $usesAccountTable && $currency && $currency !== strtoupper((string) settings('general.currency', 'EUR'))) {
            return $line.' — note: no account is set up for '.$currency.', so the default account is printed.';
        }

        return $line;
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->extraAttributes(['class' => 'font-mono']),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->description(fn (CustomInvoice $record): ?string => $record->client_company)
                    ->searchable(['client_name', 'client_company', 'client_email']),
                Tables\Columns\TextColumn::make('total')
                    ->formatStateUsing(fn ($state, CustomInvoice $record): string => format_price($state, $record->currency))
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('overdue')
                    ->label('Overdue')
                    ->state(fn (CustomInvoice $record): ?string => $record->isOverdue() ? $record->daysOverdue().'d' : null)
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance due')
                    ->state(fn (CustomInvoice $record): ?string => $record->document_type->requestsPayment() && ! in_array($record->status, [CustomInvoiceStatus::Draft, CustomInvoiceStatus::Cancelled], true)
                        ? format_price($record->balanceDue(), $record->currency)
                        : null)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('issue_date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->date('M j, Y')
                    ->sortable()
                    ->color(fn (CustomInvoice $record): ?string => $record->status === CustomInvoiceStatus::Sent && $record->due_date->isPast() ? 'danger' : null),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Emailed')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Type')
                    ->options(InvoiceDocumentType::class)
                    ->native(false),
                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue only')
                    ->toggle()
                    ->query(fn ($query) => $query
                        ->where('document_type', InvoiceDocumentType::Invoice->value)
                        ->whereIn('status', [CustomInvoiceStatus::Sent->value, CustomInvoiceStatus::PartiallyPaid->value])
                        ->whereDate('due_date', '<', now()->toDateString())),
                Tables\Filters\SelectFilter::make('status')
                    ->options(CustomInvoiceStatus::class)
                    ->native(false),
            ])
            ->recordUrl(fn (CustomInvoice $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    static::makePreviewAction(),
                    static::makeDownloadAction(),
                    static::makeSendAction(),
                    static::makeRecordPaymentAction(),
                    static::makeReminderAction(),
                    static::makeAcceptAction(),
                    static::makeDeclineAction(),
                    static::makeMarkPaidAction(),
                    ...static::makeConvertActions(),
                    static::makeCreditNoteAction(),
                    static::makeDuplicateAction(),
                    static::makeCancelAction(),
                ]),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make(static::bulkActions()),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('Nothing here yet')
            ->emptyStateDescription('Write a quotation, proforma or invoice for a client who is not ordering through the storefront, then email it to them as a PDF.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomInvoices::route('/'),
            'create' => Pages\CreateCustomInvoice::route('/create'),
            'view' => Pages\ViewCustomInvoice::route('/{record}'),
            'edit' => Pages\EditCustomInvoice::route('/{record}/edit'),
        ];
    }

    /**
     * Actions for a ticked selection of documents. Each one skips the documents it
     * cannot apply to (wrong status, no email…) and reports how many were done.
     *
     * @return array<int, Actions\BulkAction>
     */
    public static function bulkActions(): array
    {
        $service = fn (): CustomInvoiceService => app(CustomInvoiceService::class);

        return [
            self::bulkRunner('bulkSend', 'Email to clients', 'heroicon-o-paper-airplane', 'primary',
                'Each selected document is emailed to its client as a PDF; drafts become "sent". Cancelled documents and ones without a client email are skipped.',
                fn (CustomInvoice $d) => $service()->send($d)),
            self::bulkRunner('bulkRemind', 'Send payment reminders', 'heroicon-o-bell-alert', 'warning',
                'A reminder with the PDF is emailed for each sent, unpaid invoice. Everything else in the selection is skipped.',
                fn (CustomInvoice $d) => $service()->sendReminder($d)),
            self::bulkRunner('bulkMarkPaid', 'Mark as paid', 'heroicon-o-check-circle', 'success',
                'The full remaining balance of each open invoice is recorded as received today. Already paid or cancelled ones are skipped.',
                function (CustomInvoice $d) use ($service): void {
                    if (! $d->document_type->requestsPayment() || in_array($d->status, [CustomInvoiceStatus::Paid, CustomInvoiceStatus::Cancelled], true)) {
                        throw new \RuntimeException('Not payable.');
                    }
                    $service()->markPaid($d);
                }),
            self::bulkRunner('bulkCancel', 'Cancel documents', 'heroicon-o-x-circle', 'danger',
                'Drafts and sent documents are cancelled. The number stays used. Paid or already cancelled ones are skipped.',
                function (CustomInvoice $d) use ($service): void {
                    if (! in_array($d->status, [CustomInvoiceStatus::Draft, CustomInvoiceStatus::Sent], true)) {
                        throw new \RuntimeException('Not cancellable.');
                    }
                    $service()->cancel($d);
                }),
            Actions\BulkAction::make('bulkDownloadPdfs')
                ->label('Download PDFs (ZIP)')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->authorizeIndividualRecords('view')
                ->deselectRecordsAfterCompletion()
                ->modalDescription('Up to 100 documents are packed into one ZIP file.')
                ->action(function (Collection $records) use ($service) {
                    $zipPath = tempnam(sys_get_temp_dir(), 'inv');
                    $zip = new \ZipArchive;
                    $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                    foreach ($records->take(100) as $document) {
                        $zip->addFromString($service()->filename($document), $service()->pdf($document)->output());
                    }
                    $zip->close();

                    return response()->download($zipPath, 'documents-'.now()->format('Y-m-d-His').'.zip')->deleteFileAfterSend(true);
                }),
            AdminUi::exportCsvBulkAction('Export CSV', [
                'invoice_number' => 'Number',
                'document_type' => 'Type',
                'status' => 'Status',
                'client_name' => 'Client',
                'client_email' => 'Email',
                'currency' => 'Currency',
                'issue_date' => 'Issued',
                'due_date' => 'Due',
            ]),
        ];
    }

    private static function bulkRunner(string $name, string $label, string $icon, string $color, string $description, \Closure $do): Actions\BulkAction
    {
        return Actions\BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->authorizeIndividualRecords('update')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->modalHeading($label)
            ->modalDescription($description)
            ->modalSubmitActionLabel('Yes, proceed')
            ->action(function (Collection $records) use ($do, $label): void {
                $done = 0;
                $skipped = [];
                foreach ($records as $document) {
                    try {
                        $do($document);
                        $done++;
                    } catch (\Throwable $e) {
                        $skipped[] = $document->invoice_number;
                        Log::info('Bulk invoice action skipped a document', ['action' => $label, 'invoice' => $document->invoice_number, 'reason' => $e->getMessage()]);
                    }
                }

                Notification::make()
                    ->title("{$label}: {$done} done".($skipped ? ', '.count($skipped).' skipped' : ''))
                    ->body($skipped ? 'Skipped: '.implode(', ', array_slice($skipped, 0, 10)).(count($skipped) > 10 ? '…' : '') : null)
                    ->color($done > 0 ? ($skipped ? 'warning' : 'success') : 'danger')
                    ->send();
            });
    }

    /** Money arrived: record it (date, method, reference); the status follows the balance. */
    public static function makeRecordPaymentAction(): Actions\Action
    {
        return Actions\Action::make('recordPayment')
            ->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => $record->document_type->requestsPayment()
                && in_array($record->status, [CustomInvoiceStatus::Sent, CustomInvoiceStatus::PartiallyPaid], true))
            ->modalHeading(fn (CustomInvoice $record): string => 'Record a payment on '.$record->invoice_number)
            ->modalDescription(fn (CustomInvoice $record): string => 'Balance due: '.format_price($record->balanceDue(), $record->currency).'. A partial amount keeps the invoice open.')
            ->fillForm(fn (CustomInvoice $record): array => ['amount' => $record->balanceDue(), 'paid_on' => now()->toDateString()])
            ->form([
                Forms\Components\TextInput::make('amount')->numeric()->required()->minValue(0.01),
                Forms\Components\DatePicker::make('paid_on')->label('Received on')->required()->maxDate(now()),
                Forms\Components\Select::make('method')
                    ->options(['bank_transfer' => 'Bank transfer', 'card' => 'Card', 'cash' => 'Cash', 'online' => 'Online payment', 'other' => 'Other'])
                    ->native(false)
                    ->default('bank_transfer'),
                Forms\Components\TextInput::make('reference')->label('Bank reference / transaction id')->maxLength(150),
                Forms\Components\Textarea::make('note')->rows(2)->maxLength(500),
            ])
            ->action(function (CustomInvoice $record, array $data, Component $livewire): void {
                try {
                    app(CustomInvoiceService::class)->recordPayment(
                        $record,
                        $data['amount'],
                        Carbon::parse($data['paid_on']),
                        $data['method'] ?? null,
                        $data['reference'] ?? null,
                        $data['note'] ?? null,
                    );
                } catch (\Throwable $e) {
                    Notification::make()->title('Payment not recorded')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Payment recorded')->success()->send();
                $livewire->dispatch('payments-changed');
            });
    }

    /** Chase a client for an open invoice by hand (automatic reminders are a separate setting). */
    public static function makeReminderAction(): Actions\Action
    {
        return Actions\Action::make('sendReminder')
            ->label('Send payment reminder')
            ->icon('heroicon-o-bell-alert')
            ->color('warning')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => $record->document_type->requestsPayment()
                && in_array($record->status, [CustomInvoiceStatus::Sent, CustomInvoiceStatus::PartiallyPaid], true))
            ->requiresConfirmation()
            ->modalHeading('Send a payment reminder')
            ->modalDescription(fn (CustomInvoice $record): string => filled($record->client_email)
                ? "A friendly reminder with the invoice PDF will be emailed to {$record->client_email}. Reminders sent so far: {$record->reminder_count}."
                : 'This invoice has no client email address.')
            ->action(function (CustomInvoice $record): void {
                try {
                    app(CustomInvoiceService::class)->sendReminder($record);
                } catch (\Throwable $e) {
                    Notification::make()->title('Reminder not sent')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Reminder sent')->body("Emailed to {$record->client_email}.")->success()->send();
            });
    }

    private static function isDocument(CustomInvoice $record, InvoiceDocumentType ...$types): bool
    {
        return in_array($record->document_type, $types, true);
    }

    /** A quotation the client has said yes or no to. */
    public static function makeAcceptAction(): Actions\Action
    {
        return Actions\Action::make('acceptQuote')
            ->label('Mark as accepted')
            ->icon('heroicon-o-hand-thumb-up')
            ->color('success')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => self::isDocument($record, InvoiceDocumentType::Quote) && $record->status === CustomInvoiceStatus::Sent)
            ->requiresConfirmation()
            ->action(function (CustomInvoice $record): void {
                $record->forceFill(['status' => CustomInvoiceStatus::Accepted])->save();
                Notification::make()->title('Quotation marked as accepted')->success()->send();
            });
    }

    public static function makeDeclineAction(): Actions\Action
    {
        return Actions\Action::make('declineQuote')
            ->label('Mark as declined')
            ->icon('heroicon-o-hand-thumb-down')
            ->color('gray')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => self::isDocument($record, InvoiceDocumentType::Quote) && $record->status === CustomInvoiceStatus::Sent)
            ->requiresConfirmation()
            ->action(function (CustomInvoice $record): void {
                $record->forceFill(['status' => CustomInvoiceStatus::Declined])->save();
                Notification::make()->title('Quotation marked as declined')->success()->send();
            });
    }

    /** @return list<Actions\Action> one "Convert to …" action per target document type */
    public static function makeConvertActions(): array
    {
        return array_map(
            fn (InvoiceDocumentType $target): Actions\Action => Actions\Action::make('convertTo'.ucfirst($target->value))
                ->label('Convert to '.strtolower($target->getLabel()))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('primary')
                ->authorize('create')
                ->visible(fn (CustomInvoice $record): bool => in_array($target, app(CustomInvoiceService::class)->conversionTargets($record), true))
                ->requiresConfirmation()
                ->modalHeading('Convert to '.strtolower($target->getLabel()))
                ->modalDescription('A new draft with its own number is created from this document and you can edit it before sending. The original stays as it is.')
                ->action(function (CustomInvoice $record, Actions\Action $action) use ($target): void {
                    try {
                        $new = app(CustomInvoiceService::class)->convert($record, $target);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Could not convert')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title($target->getLabel().' '.$new->invoice_number.' created')->success()->send();
                    $action->redirect(static::getUrl('edit', ['record' => $new]));
                }),
            [InvoiceDocumentType::Proforma, InvoiceDocumentType::Invoice],
        );
    }

    /** Correct an issued invoice: a credit note draft linked to it. */
    public static function makeCreditNoteAction(): Actions\Action
    {
        return Actions\Action::make('issueCreditNote')
            ->label('Issue credit note')
            ->icon('heroicon-o-receipt-refund')
            ->color('danger')
            ->authorize('create')
            ->visible(fn (CustomInvoice $record): bool => self::isDocument($record, InvoiceDocumentType::Invoice)
                && in_array($record->status, [CustomInvoiceStatus::Sent, CustomInvoiceStatus::Paid], true))
            ->modalHeading('Issue a credit note')
            ->modalDescription('Creates a draft credit note with the same lines. Edit it down for a partial credit, then email it. The invoice itself is never changed.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (printed on the credit note)')
                    ->rows(2)
                    ->maxLength(500),
            ])
            ->action(function (CustomInvoice $record, array $data, Actions\Action $action): void {
                try {
                    $note = app(CustomInvoiceService::class)->issueCreditNote($record, $data['reason'] ?? null);
                } catch (\Throwable $e) {
                    Notification::make()->title('Could not issue the credit note')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Credit note '.$note->invoice_number.' created')->success()->send();
                $action->redirect(static::getUrl('edit', ['record' => $note]));
            });
    }

    public static function makeDuplicateAction(): Actions\Action
    {
        return Actions\Action::make('duplicateDocument')
            ->label('Duplicate as new draft')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->authorize('create')
            ->visible(fn (CustomInvoice $record): bool => ! self::isDocument($record, InvoiceDocumentType::CreditNote))
            ->action(function (CustomInvoice $record, Actions\Action $action): void {
                $new = app(CustomInvoiceService::class)->duplicate($record);

                Notification::make()->title('Draft '.$new->invoice_number.' created')->success()->send();
                $action->redirect(static::getUrl('edit', ['record' => $new]));
            });
    }

    /** Open the PDF in a new browser tab without downloading it (works on a draft too). */
    public static function makePreviewAction(): Actions\Action
    {
        return Actions\Action::make('previewPdf')
            ->label('Preview PDF')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->authorize('view')
            ->url(fn (CustomInvoice $record): string => route('admin.custom-invoices.pdf', ['customInvoice' => $record, 'inline' => 1]))
            ->openUrlInNewTab();
    }

    public static function makeDownloadAction(): Actions\Action
    {
        return Actions\Action::make('downloadPdf')
            ->label('Download PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->authorize('view')
            ->url(fn (CustomInvoice $record): string => route('admin.custom-invoices.pdf', ['customInvoice' => $record]));
    }

    public static function makeSendAction(): Actions\Action
    {
        return Actions\Action::make('sendToClient')
            ->label(fn (CustomInvoice $record): string => $record->sent_at ? 'Resend to client' : 'Email to client')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => $record->status !== CustomInvoiceStatus::Cancelled)
            ->modalHeading(fn (CustomInvoice $record): string => 'Email '.strtolower($record->document_type->getLabel()).' to client')
            ->modalDescription(fn (CustomInvoice $record): string => filled($record->client_email)
                ? "The PDF will be emailed to {$record->client_email}. A draft is locked for editing once sent."
                : 'This document has no client email address. Edit the draft and add one first.')
            ->modalSubmitActionLabel('Send')
            ->form([
                Forms\Components\Textarea::make('message')
                    ->label('Personal message (optional)')
                    ->rows(3)
                    ->maxLength(2000)
                    ->helperText('Shown at the top of the email, above the summary.'),
                Forms\Components\TagsInput::make('cc')
                    ->label('CC')
                    ->placeholder('Add an email and press Enter')
                    ->nestedRecursiveRules(['email']),
                Forms\Components\TagsInput::make('bcc')
                    ->label('BCC')
                    ->placeholder('Add an email and press Enter')
                    ->nestedRecursiveRules(['email']),
                Forms\Components\Toggle::make('copy_to_sender')
                    ->label('Send me a blind copy')
                    ->default(true),
            ])
            ->action(function (CustomInvoice $record, array $data): void {
                try {
                    app(CustomInvoiceService::class)->send($record, [
                        'message' => $data['message'] ?? null,
                        'cc' => $data['cc'] ?? [],
                        'bcc' => $data['bcc'] ?? [],
                        'copy_to_sender' => (bool) ($data['copy_to_sender'] ?? false),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Custom invoice email failed', [
                        'custom_invoice_id' => $record->id,
                        'invoice_number' => $record->invoice_number,
                        'error' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title('Invoice not sent')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Invoice emailed')
                    ->body("Sent to {$record->client_email}.")
                    ->success()
                    ->send();
            });
    }

    public static function makeMarkPaidAction(): Actions\Action
    {
        return Actions\Action::make('markPaid')
            ->label('Mark as paid')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => $record->document_type->requestsPayment()
                && in_array($record->status, [CustomInvoiceStatus::Draft, CustomInvoiceStatus::Sent, CustomInvoiceStatus::PartiallyPaid], true))
            ->requiresConfirmation()
            ->action(function (CustomInvoice $record): void {
                app(CustomInvoiceService::class)->markPaid($record);

                Notification::make()->title('Invoice marked as paid')->success()->send();
            });
    }

    public static function makeCancelAction(): Actions\Action
    {
        return Actions\Action::make('cancelInvoice')
            ->label('Cancel invoice')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize('update')
            ->visible(fn (CustomInvoice $record): bool => in_array($record->status, [CustomInvoiceStatus::Draft, CustomInvoiceStatus::Sent], true))
            ->requiresConfirmation()
            ->modalHeading('Cancel invoice')
            ->modalDescription('The invoice keeps its number but is marked cancelled and can no longer be sent. This cannot be undone.')
            ->action(function (CustomInvoice $record): void {
                app(CustomInvoiceService::class)->cancel($record);

                Notification::make()->title('Invoice cancelled')->success()->send();
            });
    }
}
