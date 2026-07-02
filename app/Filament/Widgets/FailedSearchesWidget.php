<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartInquiryResource;
use App\Models\FailedSearchLog;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FailedSearchesWidget extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Searches with zero results';
    }

    protected function getTableHeading(): string
    {
        $count = FailedSearchLog::where('created_at', '>=', $this->periodStart())->count();

        return 'Failed Searches (Sourcing Opportunities)' . ($count > 0 ? " ({$count})" : '');
    }

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -18;

    protected static ?string $heading = 'Failed Searches (Sourcing Opportunities)';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getTableHeaderActions(): array
    {
        return [
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
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('search_query')
                    ->label('OEM / Query')
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->description('no results — sourcing opportunity')
                    ->searchable(),
                TextColumn::make('lang')
                    ->label('Lang')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Searched')
                    ->since()
                    ->color('gray')
                    ->alignEnd(),
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
            ->striped()
            ->emptyStateIcon('heroicon-o-magnifying-glass-circle')
            ->emptyStateHeading('No failed searches')
            ->emptyStateDescription('Every search in this period returned results.')
            ->searchable(false)
            ->paginated(false);
    }
}
