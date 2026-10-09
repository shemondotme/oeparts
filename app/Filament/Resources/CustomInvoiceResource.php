<?php

namespace App\Filament\Resources;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoicePaymentMethod;
use App\Enums\InvoiceVatTreatment;
use App\Filament\Resources\CustomInvoiceResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\CustomInvoice;
use App\Models\InvoiceBankAccount;
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
use Illuminate\Support\Facades\Log;

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
        return 'Custom Invoices';
    }

    public static function getModelLabel(): string
    {
        return 'custom invoice';
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
                    ->description('Who this invoice is addressed to.')
                    ->schema([
                        Forms\Components\TextInput::make('client_name')
                            ->label('Contact name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_company')
                            ->label('Company')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_vat_number')
                            ->label('Client VAT number')
                            ->maxLength(50),
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

                Section::make('Invoice details')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Forms\Components\DatePicker::make('issue_date')
                            ->label('Issue date')
                            ->default(fn () => now())
                            ->required(),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Due date')
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
                            ->helperText('Accounts are managed under Sales → Bank Accounts; the default one is in Settings.'),
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
            return 'Nothing — no bank account is set up. Add the IBAN under Settings → Store Operations, or add an account under Sales → Bank Accounts, otherwise the invoice shows no payment details.';
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
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice no.')
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
                Tables\Filters\SelectFilter::make('status')
                    ->options(CustomInvoiceStatus::class)
                    ->native(false),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make(),
                    static::makeDownloadAction(),
                    static::makeSendAction(),
                    static::makeMarkPaidAction(),
                    static::makeCancelAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('No custom invoices yet')
            ->emptyStateDescription('Create an invoice for a client who is not ordering through the storefront, then email it to them as a PDF.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomInvoices::route('/'),
            'create' => Pages\CreateCustomInvoice::route('/create'),
            'edit' => Pages\EditCustomInvoice::route('/{record}/edit'),
        ];
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
            ->requiresConfirmation()
            ->modalHeading('Email invoice to client')
            ->modalDescription(fn (CustomInvoice $record): string => filled($record->client_email)
                ? "The invoice PDF will be emailed to {$record->client_email}. A draft is locked for editing once sent."
                : 'This invoice has no client email address. Edit the draft and add one first.')
            ->modalSubmitActionLabel('Send invoice')
            ->action(function (CustomInvoice $record): void {
                try {
                    app(CustomInvoiceService::class)->send($record);
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
            ->visible(fn (CustomInvoice $record): bool => in_array($record->status, [CustomInvoiceStatus::Draft, CustomInvoiceStatus::Sent], true))
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
