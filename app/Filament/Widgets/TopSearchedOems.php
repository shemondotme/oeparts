<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\SearchLog;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopSearchedOems extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Most popular search queries';
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -25;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getTableHeading(): string
    {
        return 'Top Searched OEMs (' . $this->periodLabel() . ')';
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\SearchLogResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SearchLog::query()
                    ->select('search_query', DB::raw('COUNT(*) as search_count'), DB::raw('MIN(id) as id'))
                    ->where('created_at', '>=', $this->periodStart())
                    ->groupBy('search_query')
                    ->orderByDesc('search_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->badge()
                    ->icon(fn (mixed $state): ?string => (int) $state === 1 ? 'heroicon-m-trophy' : null)
                    ->color(fn (mixed $state): string => match ((int) $state) {
                        1 => 'warning',
                        2, 3 => 'primary',
                        default => 'gray',
                    })
                    ->alignCenter(),
                TextColumn::make('search_query')
                    ->label('OEM / Query')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description('search term')
                    ->searchable(),
                TextColumn::make('search_count')
                    ->label('Searches')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-magnifying-glass')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state) . '×')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('source_now')
                    ->label('Source Now')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->size('sm')
                    ->url(fn ($record): string => ProductResource::getUrl('create', ['data' => ['oem_number' => $record->search_query]])),
                Tables\Actions\Action::make('search')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn ($record): string => ProductResource::getUrl('index', ['tableSearch' => $record->search_query]))
                    ->openUrlInNewTab(),
            ])
            ->striped()
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->emptyStateHeading('No searches yet')
            ->emptyStateDescription('Customer search activity will appear here.')
            ->searchable(false)
            ->paginated(false);
    }
}
