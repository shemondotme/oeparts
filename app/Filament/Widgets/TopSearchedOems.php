<?php

namespace App\Filament\Widgets;

use App\Models\SearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopSearchedOems extends TableWidget
{
    protected static ?int $sort = -16;

    protected static ?string $heading = 'Top Searched OEMs (7 days)';

    protected static ?string $maxWidth = '1/2';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SearchLog::query()
                    ->select('search_query', DB::raw('COUNT(*) as search_count'))
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('search_query')
                    ->orderByDesc('search_count')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('search_query')
                    ->label('OEM Number')
                    ->searchable()
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('search_count')
                    ->label('Searches')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
