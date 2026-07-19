<?php

namespace App\Filament\Widgets\Reports;

use App\Models\SearchLog;
use Carbon\Carbon;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SearchTopSearches extends TableWidget
{
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected static ?string $heading = 'Top Searches';

    public function table(Table $table): Table
    {
        $start = $this->periodStart();

        return $table
            ->query(
                SearchLog::query()
                    ->where('created_at', '>=', $start)
                    ->select(
                        DB::raw('MIN(id) as id'),
                        'search_query',
                        DB::raw('COUNT(*) as count'),
                    )
                    ->groupBy('search_query')
                    ->orderByDesc('count')
                    ->limit(15)
            )
            // See SalesTopProducts::table() — Filament's default pagination-stable
            // "ORDER BY {table}.id" is invalid here too (id isn't in the GROUP BY,
            // only MIN(id) is selected), and hits the same live 500 under MySQL's
            // only_full_group_by. count DESC above is already the intended sort.
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('search_query')
                    ->label('Query')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),
                TextColumn::make('count')
                    ->label('Searches')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state) . '×')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->emptyStateHeading('No searches')
            ->emptyStateDescription('No search activity in this period.');
    }
}
