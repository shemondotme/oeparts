<?php

namespace App\Filament\Widgets;

use App\Models\FailedJob;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class FailedQueueJobsMonitor extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use Concerns\InteractsWithDashboardCache;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Failed Queue Jobs';

    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -30;

    public function getDescription(): ?string
    {
        return 'Monitor for failed queue job retries';
    }

    protected function getTableHeading(): string
    {
        $count = DB::table('failed_jobs')->count();

        return 'Failed Queue Jobs' . ($count > 0 ? " ({$count})" : '');
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(\App\Filament\Pages\System\FailedJobsPage::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FailedJob::query()
                    ->orderByDesc('failed_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('payload')
                    ->label('Job')
                    ->getStateUsing(function ($record): string {
                        $payload = json_decode($record->payload ?? '{}', true);
                        $command = $payload['displayName'] ?? $payload['job'] ?? 'Unknown';
                        return class_basename($command);
                    })
                    ->weight(FontWeight::Bold)
                    ->fontFamily(FontFamily::Mono)
                    ->limit(40)
                    ->tooltip(function ($record): ?string {
                        $payload = json_decode($record->payload ?? '{}', true);
                        $command = $payload['displayName'] ?? $payload['job'] ?? 'Unknown';
                        return mb_strlen((string) $command) > 40 ? $command : null;
                    })
                    ->description(fn ($record): string => ($record->connection ?? '—') . ' · ' . ($record->queue ?? 'default')),
                TextColumn::make('failed_flag')
                    ->label('Status')
                    ->state('Failed')
                    ->badge()
                    ->icon('heroicon-m-x-circle')
                    ->color('danger'),
                TextColumn::make('failed_at')
                    ->label('Failed At')
                    ->since()
                    ->color('danger')
                    ->weight(FontWeight::Medium)
                    ->description('failed')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->size('sm')
                    ->action(function ($record): void {
                        // queue:retry handles payload decoding (incl. encrypted
                        // commands) and removes the failed_jobs row itself —
                        // hand-unserializing here silently no-opped on failure.
                        \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        $requeued = ! DB::table('failed_jobs')->where('uuid', $record->uuid)->exists();

                        \Filament\Notifications\Notification::make()
                            ->title($requeued ? 'Job pushed back onto the queue' : 'Retry failed')
                            ->body($requeued ? null : 'The job could not be re-dispatched — retry it from the CLI with queue:retry.')
                            ->{$requeued ? 'success' : 'danger'}()
                            ->send();
                    }),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->action(fn ($record) => DB::table('failed_jobs')->where('id', $record->id)->delete()),
            ])
            ->recordClasses(fn ($record): ?string => ($record->queue ?? 'default') === 'critical' ? 'op-row-critical' : null)
            ->striped()
            ->paginated(false)
            ->searchable(false)
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('Queue is healthy')
            ->emptyStateDescription('No failed jobs — all workers are processing normally.');
    }
}
