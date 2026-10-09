<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceClientResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\InvoiceClient;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\VatNumberChecker;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceClientResource extends Resource
{
    protected static ?string $model = InvoiceClient::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 14;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Sales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Invoice Clients';
    }

    public static function getModelLabel(): string
    {
        return 'client';
    }

    /**
     * The "Check VAT number" button shared by the client and invoice forms.
     *
     * @param  string  $vatField  form field holding the VAT number
     * @param  string  $countryField  form field holding the country (used when the number has no prefix)
     */
    public static function vatCheckAction(string $vatField, string $countryField): Actions\Action
    {
        return Actions\Action::make('checkVat')
            ->label('Check in VIES')
            ->icon('heroicon-o-check-badge')
            ->tooltip('Check this EU VAT number in the European Commission VIES service')
            ->action(function (Get $get) use ($vatField, $countryField): void {
                $result = app(VatNumberChecker::class)->check($get($vatField), $get($countryField));

                $notification = Notification::make()->title('VAT number check')->body($result['message']);

                match ($result['status']) {
                    'valid' => $notification->success(),
                    'invalid' => $notification->danger(),
                    default => $notification->warning(),
                };

                $notification->send();
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client')
                    ->icon('heroicon-o-user-group')
                    ->description('Saved once, picked on any quotation, proforma or invoice.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Registered customer (optional)')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                /** @var User|null $user */
                                $user = $state ? User::with('addresses')->find($state) : null;
                                if (! $user) {
                                    return;
                                }

                                /** @var UserAddress|null $address */
                                $address = $user->addresses->sortByDesc('is_default')->first();

                                $addressName = $address ? trim($address->first_name.' '.$address->last_name) : '';
                                $set('name', $addressName !== '' ? $addressName : $user->name);
                                $set('email', $user->email);
                                $set('phone', $address?->phone ?: $user->phone);
                                if ($address) {
                                    $set('company', $address->company);
                                    $set('address_line1', $address->address_line1);
                                    $set('address_line2', $address->address_line2);
                                    $set('city', $address->city);
                                    $set('state', $address->state);
                                    $set('postal_code', $address->postal_code);
                                    $set('country_code', $address->country_code);
                                }
                            })
                            ->helperText('Picking an account fills in its name, email and saved address.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('name')
                            ->label('Contact name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('company')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('vat_number')
                            ->label('VAT number')
                            ->maxLength(50)
                            ->suffixAction(static::vatCheckAction('vat_number', 'country_code')),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\Select::make('currency')
                            ->label('Usual currency')
                            ->options(InvoiceBankAccountResource::currencies())
                            ->native(false)
                            ->placeholder('No preference'),
                        Forms\Components\TextInput::make('address_line1')
                            ->label('Address line 1')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('address_line2')
                            ->label('Address line 2')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('state')
                            ->label('State / region')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('postal_code')
                            ->maxLength(20),
                        Forms\Components\Select::make('country_code')
                            ->label('Country')
                            ->options(config('countries', []))
                            ->searchable()
                            ->native(false)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal notes')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Contact')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->label('Country')
                    ->badge(),
                Tables\Columns\TextColumn::make('vat_number')
                    ->label('VAT no.')
                    ->extraAttributes(['class' => 'font-mono'])
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Documents')
                    ->counts('invoices')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('newInvoice')
                    ->label('New invoice')
                    ->icon('heroicon-o-document-plus')
                    ->color('primary')
                    ->authorize('create', CustomInvoiceResource::getModel())
                    ->url(fn (InvoiceClient $record): string => CustomInvoiceResource::getUrl('create', ['client' => $record->id])),
            ])
            ->defaultSort('company')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No saved clients yet')
            ->emptyStateDescription('Save a client once and pick them on any quotation or invoice instead of retyping their address.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceClients::route('/'),
            'create' => Pages\CreateInvoiceClient::route('/create'),
            'edit' => Pages\EditInvoiceClient::route('/{record}/edit'),
        ];
    }
}
