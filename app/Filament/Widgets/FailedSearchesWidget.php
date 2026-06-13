<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PartInquiryResource;
use App\Models\FailedSearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FailedSearchesWidget extends TableWidget
{
    public function getDescription(): ?string
    {
        return 'Searches with zero results';
    }

    use \App\Filament\Widgets\Concerns\HasDashboardPeriod;
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = -32;

    protected static ?string $heading = 'Failed Searches (Sourcing Opportunities)';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

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
                Tables\Actions\Action::make('inquire')
                    ->label('Inquire')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->url(fn ($record): string => PartInquiryResource::getUrl('create', ['data' => ['search' => $record->search_query]])),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
