<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\ProductResource;
use App\Models\SearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TopSearchedOems extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Most popular search queries';
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -25;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    public function getHeading(): string
    {
        return 'Top Searched OEMs (' . $this->periodLabel() . ')';
    }

    protected function getExportHeaders(): array
    {
        return ['OEM Number', 'Searches'];
    }

    protected function getExportRows(): iterable
    {
        return SearchLog::query()
            ->select('search_query', \Illuminate\Support\Facades\DB::raw('COUNT(*) as search_count'))
            ->where('created_at', '>=', $this->periodStart())
            ->groupBy('search_query')
            ->orderByDesc('search_count')
            ->get()
            ->map(fn ($row) => [
                $row->search_query,
                $row->search_count,
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions()];
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
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-magnifying-glass',
                    'heading' => 'No search logs yet',
                    'description' => 'Enable search tracking in settings to see OEM search data.',
                    'ctaLabel' => '',
                    'ctaUrl' => '',
                ])
            )
            ->searchable(false)
            ->paginated(false);
    }
}
