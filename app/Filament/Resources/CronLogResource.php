<?php

namespace App\Filament\Resources;

use App\Enums\LogStatus;
use App\Filament\Resources\CronLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\CronLog;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class CronLogResource extends Resource
{
    protected static ?string $model = CronLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CronLog::where('status', 'failed')->whereDate('ran_at', today())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?int $navigationSort = 120;

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('job_name')
                    ->label('Job Name')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Result')
                    ->badge()
                    ->color(fn (LogStatus $state): string => $state === LogStatus::Success ? 'success' : 'danger')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->sortable()
                    ->alignCenter()
                    ->fontMono(),
                Tables\Columns\TextColumn::make('output')
                    ->label('Output')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ran_at')
                    ->label('Ran At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('ran_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Result')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                    ])
                    ->helperText('Filter cron jobs by success or failure status.'),
            ])
            ->actions([
                ...AdminUi::recordActionsReadOnly(),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('No cron jobs logged yet')
            ->emptyStateDescription('Scheduled task execution logs will appear here, showing job status and duration.')
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Cron Logs', [
                        'job_name' => 'Job Name',
                        'status' => 'Result',
                        'duration_ms' => 'Duration (ms)',
                        'output' => 'Output',
                        'ran_at' => 'Ran At',
                    ]),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCronLogs::route('/'),
            'view' => Pages\ViewCronLog::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['job_name'];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
