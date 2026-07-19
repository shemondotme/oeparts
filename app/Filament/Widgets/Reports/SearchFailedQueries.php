<?php

namespace App\Filament\Widgets\Reports;

use App\Models\FailedSearchLog;
use Carbon\Carbon;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SearchFailedQueries extends TableWidget
{
    use \App\Filament\Widgets\Reports\Concerns\HasReportPeriod;

    protected static ?string $heading = 'Failed Queries (Sourcing Opportunities)';

    public function table(Table $table): Table
    {
        $start = $this->periodStart();

        return $table
            ->query(
                FailedSearchLog::query()
                    ->where('created_at', '>=', $start)
                    ->where('inquiry_submitted', false)
                    ->select(
                        DB::raw('MIN(id) as id'),
                        'search_query',
                        DB::raw('COUNT(*) as count'),
                    )
                    ->groupBy('search_query')
                    ->orderByDesc('count')
                    ->limit(10)
            )
            // See SalesTopProducts::table() — Filament's default pagination-stable
            // "ORDER BY {table}.id" is invalid here too (id isn't in the GROUP BY,
            // only MIN(id) is selected), and hits the same live 500 under MySQL's
            // only_full_group_by. count DESC above is already the intended sort.
            ->defaultKeySort(false)
            ->columns([
                TextColumn::make('search_query')
                    ->label('OEM / Query')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::SemiBold)
                    ->description('no results — sourcing opportunity')
                    ->searchable(),
                TextColumn::make('count')
                    ->label('Attempts')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state) . '×')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('No failed searches')
            ->emptyStateDescription('Every search returned results in this period.');
    }
}
