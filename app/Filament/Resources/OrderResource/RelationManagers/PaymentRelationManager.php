<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Filament\Support\AdminUi;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'transaction_id';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('transaction_id')
            ->columns([
                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
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
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->copyable()
                    ->fontMono()
                    ->limit(30)
                    ->default('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PaymentTransactionStatus $state): string => match ($state) {
                        PaymentTransactionStatus::Pending => 'Pending',
                        PaymentTransactionStatus::Authorized => 'Authorized',
                        PaymentTransactionStatus::Captured => 'Captured',
                        PaymentTransactionStatus::Failed => 'Failed',
                        PaymentTransactionStatus::Refunded => 'Refunded',
                    })
                    ->color(fn (PaymentTransactionStatus $state): string => AdminUi::paymentStatusColor($state)),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->getStateUsing(fn ($record): string => format_money($record->amount))
                    ->alignEnd()
                    ->fontMono()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
