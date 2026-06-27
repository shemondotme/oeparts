<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\PartInquiryResource;
use App\Models\FailedSearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FailedSearchesWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Searches with zero results';
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -18;

    protected static ?string $heading = 'Failed Searches (Sourcing Opportunities)';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getExportHeaders(): array
    {
        return ['OEM Number', 'Language', 'Date'];
    }

    protected function getExportRows(): iterable
    {
        return FailedSearchLog::query()
            ->where('created_at', '>=', $this->periodStart())
            ->latest()
            ->get()
            ->map(fn (FailedSearchLog $row) => [
                $row->search_query,
                $row->lang ?? '—',
                $row->created_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            $this->getExportActions(),
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Resources\FailedSearchLogResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FailedSearchLog::query()
                    ->where('created_at', '>=', $this->periodStart())
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
            ->actions([
                Tables\Actions\Action::make('create_product')
                    ->label('Create Product')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->size('sm')
                    ->url(fn ($record): string => \App\Filament\Resources\ProductResource::getUrl('create', ['data' => ['oem_number' => $record->search_query]])),
                Tables\Actions\Action::make('inquire')
                    ->label('Inquire')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->size('sm')
                    ->url(fn ($record): string => PartInquiryResource::getUrl('create', ['data' => ['search' => $record->search_query]])),
            ])
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-hand-thumb-up',
                    'heading' => 'No failed searches',
                    'description' => 'Customers are finding what they need!',
                    'ctaLabel' => '',
                    'ctaUrl' => '',
                ])
            )
            ->searchable(false)
            ->paginated(false);
    }
}
