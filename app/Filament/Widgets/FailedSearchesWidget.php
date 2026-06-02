<?php

namespace App\Filament\Widgets;

use App\Models\FailedSearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FailedSearchesWidget extends TableWidget
{
    protected static ?int $sort = -14;

    protected static ?string $heading = 'Failed Searches (Sourcing Opportunities)';

    protected static ?string $maxWidth = '1/2';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FailedSearchLog::query()
                    ->where('created_at', '>=', now()->subDays(7))
                    ->latest()
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('search_query')
                    ->label('OEM Number')
                    ->searchable()
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('lang')
                    ->label('Language')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
