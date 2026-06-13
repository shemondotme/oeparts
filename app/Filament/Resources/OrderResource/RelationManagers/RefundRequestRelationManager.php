<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Enums\RefundStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RefundRequestRelationManager extends RelationManager
{
    protected static string $relationship = 'refundRequests';

    protected static ?string $recordTitleAttribute = 'reason';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('reason')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount_requested')
                    ->label('Amount Requested')
                    ->getStateUsing(fn ($record): string => format_money($record->amount_requested))
                    ->fontMono()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        RefundStatus::Pending => 'Pending',
                        RefundStatus::Approved => 'Approved',
                        RefundStatus::Rejected => 'Rejected',
                        RefundStatus::Processed => 'Processed',
                        default => $state->value ?? (string) $state,
                    })
                    ->color(fn ($state): string => match ($state) {
                        RefundStatus::Pending => 'warning',
                        RefundStatus::Approved => 'success',
                        RefundStatus::Rejected => 'danger',
                        RefundStatus::Processed => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
