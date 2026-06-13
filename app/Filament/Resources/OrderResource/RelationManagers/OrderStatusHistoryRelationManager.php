<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Filament\Support\AdminUi;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderStatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $recordTitleAttribute = 'new_status';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->recordTitleAttribute('new_status')
            ->columns([
                Tables\Columns\TextColumn::make('old_status')
                    ->label('From')
                    ->badge()
                    ->color(fn ($state): string => AdminUi::orderStatusColor($state)),
                Tables\Columns\TextColumn::make('new_status')
                    ->label('To')
                    ->badge()
                    ->color(fn ($state): string => AdminUi::orderStatusColor($state)),
                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(50),
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('By')
                    ->getStateUsing(fn ($record): string => $record->admin?->name ?? 'System'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M j, Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
