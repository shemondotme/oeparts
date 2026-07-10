<?php

namespace App\Filament\Resources;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Payment;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'payments';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-credit-card';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Commerce';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'transaction_id';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['transaction_id'];
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('order'))
            ->columns([
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->fontMono()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('order.order_number')
                ->label('Order Number')
                ->searchable()
                ->sortable()
                ->url(fn ($record): string => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record->order_id]))
                ->color('primary')
                ->fontMono(),

            Tables\Columns\TextColumn::make('gateway')
                ->label('Payment Gateway')
                ->badge()
                ->formatStateUsing(fn (PaymentGateway $state): string => match ($state) {
                    PaymentGateway::Airwallex => 'Airwallex',
                    PaymentGateway::BankTransfer => 'Bank Transfer',
                })
                ->color(fn (PaymentGateway $state): string => match ($state) {
                    PaymentGateway::Airwallex => 'info',
                    PaymentGateway::BankTransfer => 'warning',
                })
                ->icon(fn (PaymentGateway $state): string => match ($state) {
                    PaymentGateway::Airwallex => 'heroicon-o-globe-alt',
                    PaymentGateway::BankTransfer => 'heroicon-o-building-library',
                })
                ->sortable(),

            AdminUi::copyableColumn('transaction_id', 'Transaction ID', 'Transaction ID copied')
                ->limit(30)
                ->default('—')
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('status')
                ->label('Payment Status')
                ->badge()
                ->formatStateUsing(fn (PaymentTransactionStatus $state): string => match ($state) {
                    PaymentTransactionStatus::Pending => 'Pending',
                    PaymentTransactionStatus::Authorized => 'Authorized',
                    PaymentTransactionStatus::Captured => 'Captured',
                    PaymentTransactionStatus::Failed => 'Failed',
                    PaymentTransactionStatus::Refunded => 'Refunded',
                })
                ->color(fn (PaymentTransactionStatus $state): string => AdminUi::paymentStatusColor($state))
                ->icon(fn (PaymentTransactionStatus $state): string => match ($state) {
                    PaymentTransactionStatus::Pending => 'heroicon-o-clock',
                    PaymentTransactionStatus::Authorized => 'heroicon-o-lock-closed',
                    PaymentTransactionStatus::Captured => 'heroicon-o-check-circle',
                    PaymentTransactionStatus::Failed => 'heroicon-o-x-circle',
                    PaymentTransactionStatus::Refunded => 'heroicon-o-banknotes',
                })
                ->sortable(),

            Tables\Columns\TextColumn::make('amount')
                ->label('Amount')
                ->formatStateUsing(fn ($state): string => format_money($state))
                ->sortable()
                ->fontMono()
                ->weight('bold')
                ->alignEnd()
                ->extraAttributes(['class' => 'op-payment-amount']),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Date')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->fontMono(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->emptyStateHeading('No payments recorded')
            ->emptyStateDescription('Payment records will appear here once customers complete checkout. Failed payments will be flagged for review.')
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label('Payment Gateway')
                    ->options(PaymentGateway::class)
                    ->multiple()
                    ->native(false)
                    ->helperText('Filter by payment provider (Airwallex or Bank Transfer).'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Payment Status')
                    ->options(PaymentTransactionStatus::class)
                    ->multiple()
                    ->native(false)
                    ->helperText('Filter by one or more payment statuses.'),
                Tables\Filters\Filter::make('created_at')
                    ->label('Payment Date')
                    ->form([
                        \Filament\Forms\Components\Select::make('created_at')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'quarter' => 'This Quarter',
                            ])
                            ->placeholder('All Time'),
                    ])
                    ->query(function ($query, array $data): void {
                        if (empty($data['created_at'])) {
                            return;
                        }

                        match ($data['created_at']) {
                            'today' => $query->whereDate('created_at', now()->toDateString()),
                            'yesterday' => $query->whereDate('created_at', now()->subDay()->toDateString()),
                            'week' => $query->where('created_at', '>=', now()->startOfWeek()),
                            'month' => $query->where('created_at', '>=', now()->startOfMonth()),
                            'quarter' => $query->where('created_at', '>=', now()->startOfQuarter()),
                            default => $query,
                        };
                    }),
            ])
        ->actions([
            ...AdminUi::recordActions(),
        ])
        ->bulkActions([
            BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Payments', [
                    'id' => 'ID',
                    'order.order_number' => 'Order Number',
                    'gateway' => 'Gateway',
                    'transaction_id' => 'Transaction ID',
                    'status' => 'Status',
                    'amount' => 'Amount',
                    'created_at' => 'Date',
                ]),
                DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count('payments_failed', fn () => static::getModel()::where('status', 'failed')->count());
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Payment Details')
                                    ->icon('heroicon-o-credit-card')
                                    ->description('Transaction information from the payment gateway.')
                                    ->schema([
                                        TextEntry::make('order.order_number')
                                            ->label('Order Number')
                                            ->url(fn ($record): string => \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $record->order_id]))
                                            ->color('primary'),
                                        TextEntry::make('gateway')
                                            ->label('Payment Gateway')
                                            ->badge()
                                            ->formatStateUsing(fn (PaymentGateway $state): string => match ($state) {
                                                PaymentGateway::Airwallex => 'Airwallex',
                                                PaymentGateway::BankTransfer => 'Bank Transfer',
                                            })
                                            ->color(fn (PaymentGateway $state): string => match ($state) {
                                                PaymentGateway::Airwallex => 'info',
                                                PaymentGateway::BankTransfer => 'warning',
                                            })
                                            ->icon(fn (PaymentGateway $state): string => match ($state) {
                                                PaymentGateway::Airwallex => 'heroicon-o-globe-alt',
                                                PaymentGateway::BankTransfer => 'heroicon-o-building-library',
                                            }),
                                        TextEntry::make('transaction_id')
                                            ->label('Transaction ID')
                                            ->copyable()
                                            ->copyMessage('Transaction ID copied')
                                            ->fontMono()
                                            ->default('—'),
                                        TextEntry::make('status')
                                            ->label('Payment Status')
                                            ->badge()
                                            ->formatStateUsing(fn (PaymentTransactionStatus $state): string => match ($state) {
                                                PaymentTransactionStatus::Pending => 'Pending',
                                                PaymentTransactionStatus::Authorized => 'Authorized',
                                                PaymentTransactionStatus::Captured => 'Captured',
                                                PaymentTransactionStatus::Failed => 'Failed',
                                                PaymentTransactionStatus::Refunded => 'Refunded',
                                            })
                                            ->color(fn (PaymentTransactionStatus $state): string => AdminUi::paymentStatusColor($state))
                                            ->icon(fn (PaymentTransactionStatus $state): string => match ($state) {
                                                PaymentTransactionStatus::Pending => 'heroicon-o-clock',
                                                PaymentTransactionStatus::Authorized => 'heroicon-o-lock-closed',
                                                PaymentTransactionStatus::Captured => 'heroicon-o-check-circle',
                                                PaymentTransactionStatus::Failed => 'heroicon-o-x-circle',
                                                PaymentTransactionStatus::Refunded => 'heroicon-o-banknotes',
                                            }),
                                        TextEntry::make('amount')
                                            ->label('Payment Amount')
                                            ->formatStateUsing(fn ($state): string => format_money($state))
                                            ->weight('bold')
                                            ->extraAttributes(['class' => 'op-payment-amount']),
                                    ])
                                    ->columns(2),
                            ]),
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Order Summary')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->description('Related order details for this payment.')
                                    ->schema([
                                        TextEntry::make('order.status')
                                            ->label('Order Status')
                                            ->badge()
                                            ->color(fn ($state): string => AdminUi::orderStatusColor($state)),
                                        TextEntry::make('order.payment_status')
                                            ->label('Payment Status')
                                            ->badge()
                                            ->color(fn ($state): string => AdminUi::paymentStatusColor($state)),
                                        TextEntry::make('order.grand_total')
                                            ->label('Order Total')
                                            ->formatStateUsing(fn ($state): string => format_money($state))
                                            ->weight('bold'),
                                        TextEntry::make('order.created_at')
                                            ->label('Order Placed')
                                            ->dateTime('M j, Y H:i'),
                                    ]),
                                Section::make('Timestamps')
                                    ->icon('heroicon-o-clock')
                                    ->description('Payment processing timeline.')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Payment Received')
                                            ->dateTime('M j, Y H:i:s'),
                                        TextEntry::make('updated_at')
                                            ->label('Last Updated')
                                            ->dateTime('M j, Y H:i:s'),
                                    ]),
                            ]),
                    ]),
                Section::make('Gateway Response')
                    ->icon('heroicon-o-code-bracket')
                    ->description('Raw response data from the payment gateway for debugging and verification.')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('gateway_response')
                            ->label('Response Data')
                            ->formatStateUsing(function ($state): string {
                                if (empty($state)) {
                                    return 'No response data recorded from the payment gateway.';
                                }

                                return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            })
                            ->fontMono()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
