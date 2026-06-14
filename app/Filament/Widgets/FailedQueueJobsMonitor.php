<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Models\FailedJob;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class FailedQueueJobsMonitor extends TableWidget
{
    use Concerns\HasWidgetRoles;
    use HasWidgetExport;

    protected static bool $isLazy = true;

    protected static ?string $heading = 'Failed Queue Jobs';

    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -30;

    public function getDescription(): ?string
    {
        return 'Monitor for failed queue job retries';
    }

    protected function getExportHeaders(): array
    {
        return ['Connection', 'Queue', 'Job', 'Failed At'];
    }

    protected function getExportRows(): iterable
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get()
            ->map(function (object $row): array {
                $payload = json_decode($row->payload ?? '{}', true);
                $job = class_basename($payload['displayName'] ?? $payload['job'] ?? 'Unknown');
                return [
                    $row->connection,
                    $row->queue,
                    $job,
                    $row->failed_at ?? '—',
                ];
            });
    }

    protected function getHeaderActions(): array
    {
        return [$this->getExportActions()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FailedJob::query()
                    ->orderByDesc('failed_at')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('connection')
                    ->label('Connection')
                    ->badge()
                    ->color('gray')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('queue')
                    ->label('Queue')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('payload')
                    ->label('Job')
                    ->getStateUsing(function ($record): string {
                        $payload = json_decode($record->payload ?? '{}', true);
                        $command = $payload['displayName'] ?? $payload['job'] ?? 'Unknown';
                        $class = class_basename($command);
                        return $class;
                    })
                    ->limit(30)
                    ->size('sm'),
                Tables\Columns\TextColumn::make('failed_at')
                    ->label('Failed')
                    ->since()
                    ->size('sm'),
            ])
            ->actions([
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->size('sm')
                    ->action(function ($record): void {
                        $payload = json_decode($record->payload ?? '{}', true);
                        $command = unserialize($payload['data']['command'] ?? '') ?? null;
                        if ($command) {
                            dispatch($command);
                            DB::table('failed_jobs')->where('id', $record->id)->delete();
                        }
                    }),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->action(fn ($record) => DB::table('failed_jobs')->where('id', $record->id)->delete()),
            ])
            ->paginated(false)
            ->searchable(false);
    }
}
