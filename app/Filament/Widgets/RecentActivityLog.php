<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivityLog extends TableWidget
{
    protected static ?int $sort = -9;

    protected static ?string $heading = 'Recent Admin Activity';

    protected static ?string $maxWidth = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ActivityLog::query()
                    ->with('admin')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->searchable()
                    ->getStateUsing(fn (ActivityLog $record): string => $record->admin?->name ?? 'System')
                    ->limit(20),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->getStateUsing(fn (ActivityLog $record): string => $record->model_type
                        ? class_basename($record->model_type)
                        : '—')
                    ->limit(20),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
