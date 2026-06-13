<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProductResource;
use App\Models\SearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopSearchedOems extends TableWidget
{
    public function getDescription(): ?string
    {
        return 'Most popular search queries';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -33;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getHeading(): string
    {
        return 'Top Searched OEMs (' . $this->periodLabel() . ')';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SearchLog::query()
                    ->select('search_query', DB::raw('COUNT(*) as search_count'))
                    ->where('created_at', '>=', $this->periodStart())
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
            ->actions([
                Tables\Actions\Action::make('search')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->url(fn ($record): string => ProductResource::getUrl('index', ['tableSearch' => $record->search_query]))
                    ->openUrlInNewTab(),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
