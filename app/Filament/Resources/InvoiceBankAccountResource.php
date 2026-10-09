<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceBankAccountResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\InvoiceBankAccount;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceBankAccountResource extends Resource
{
    protected static ?string $model = InvoiceBankAccount::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?int $navigationSort = 16;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-building-library';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Sales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bank Accounts';
    }

    public static function getModelLabel(): string
    {
        return 'bank account';
    }

    /** Currencies an invoice can be issued in (shared with the custom-invoice form). */
    public static function currencies(): array
    {
        return [
            'EUR' => 'EUR — Euro',
            'USD' => 'USD — US dollar',
            'GBP' => 'GBP — Pound sterling',
            'JPY' => 'JPY — Japanese yen',
            'PLN' => 'PLN — Złoty',
            'SEK' => 'SEK — Swedish krona',
            'NOK' => 'NOK — Norwegian krone',
            'DKK' => 'DKK — Danish krone',
            'CHF' => 'CHF — Swiss franc',
            'CZK' => 'CZK — Czech koruna',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->icon('heroicon-o-building-library')
                    ->description('Printed on invoices as "how to pay". The account saved in Settings stays the default; add more here, for example one per currency or an international one with SWIFT.')
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->label('Internal name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Swedbank EUR, Wise USD')
                            ->helperText('Only you see this; it is how you pick the account on an invoice.'),
                        Forms\Components\Select::make('currency')
                            ->label('Use automatically for')
                            ->options(static::currencies())
                            ->native(false)
                            ->placeholder('No automatic use (pick it by hand)')
                            ->helperText('An invoice in this currency, left on "Automatic", prints this account.'),
                        Forms\Components\TextInput::make('account_holder')
                            ->required()
                            ->maxLength(150)
                            ->helperText('The exact name on the account.'),
                        Forms\Components\TextInput::make('bank_name')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('iban')
                            ->label('IBAN / account number')
                            ->required()
                            ->maxLength(40)
                            ->extraInputAttributes(['class' => 'font-mono'])
                            ->rules([
                                fn () => function (string $attribute, $value, \Closure $fail): void {
                                    // Real IBANs are checksummed. Accounts outside the IBAN world
                                    // (US, JP, ...) can still be entered: anything that does not even
                                    // look like an IBAN is accepted as a plain account number.
                                    $compact = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');
                                    if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $compact) && ! InvoiceBankAccount::isValidIban($compact)) {
                                        $fail('This looks like an IBAN but its check digits are wrong. Please re-check it: a typo here sends payments to the wrong account.');
                                    }
                                },
                            ]),
                        Forms\Components\TextInput::make('bic')
                            ->label('SWIFT / BIC')
                            ->maxLength(20)
                            ->extraInputAttributes(['class' => 'font-mono'])
                            ->helperText('Needed for payments from outside the EU/SEPA area.'),
                        Forms\Components\Textarea::make('intermediary_bank')
                            ->label('Intermediary / correspondent bank')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Optional. Name and SWIFT of the correspondent bank if your bank requires one for international transfers.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('instructions')
                            ->label('Extra payment instructions')
                            ->rows(2)
                            ->maxLength(1000)
                            ->helperText('Optional line printed under the account, e.g. "Charges: OUR" or "Please state the invoice number".')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive accounts are never printed on new invoices.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Name')
                    ->searchable()
                    ->weight(FontWeight::Medium),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Auto for')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('account_holder')->toggleable(),
                Tables\Columns\TextColumn::make('iban')
                    ->label('IBAN')
                    ->formatStateUsing(fn (string $state): string => trim(chunk_split($state, 4, ' ')))
                    ->extraAttributes(['class' => 'font-mono'])
                    ->copyable(),
                Tables\Columns\TextColumn::make('bic')->label('BIC')->extraAttributes(['class' => 'font-mono'])->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->emptyStateIcon('heroicon-o-building-library')
            ->emptyStateHeading('No extra bank accounts')
            ->emptyStateDescription('Invoices use the bank account saved in Settings. Add accounts here to offer a different one per currency or an international account.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceBankAccounts::route('/'),
            'create' => Pages\CreateInvoiceBankAccount::route('/create'),
            'edit' => Pages\EditInvoiceBankAccount::route('/{record}/edit'),
        ];
    }
}
