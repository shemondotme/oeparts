<?php

namespace App\Filament\Resources\AdminResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $recordTitleAttribute = 'action';

    protected static bool $isReadOnly = true;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->getStateUsing(fn ($record): string => $record->model_type ? class_basename($record->model_type) : '—'),
                Tables\Columns\TextColumn::make('model_id')
                    ->label('ID')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options(fn (): array => $this->getRelatedModel()::distinct()->pluck('action', 'action')->toArray()),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private function getRelatedModel(): string
    {
        return $this->getRelationship()->getRelated()::class;
    }
}
