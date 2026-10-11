<?php

namespace App\Filament\Widgets\Cleanup;

use App\Filament\Pages\System\BackupDashboard;
use App\Filament\Pages\System\CleanupDashboard;
use App\Models\BackupRun;
use App\Services\Backup\BackupRetentionService;
use App\Services\Cleanup\CleanupText;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * What backup retention would keep and what it would remove right now: exactly the decision the real run takes, because it
 * is the same {@see BackupRetentionService::plan()}. Read-only; the buttons that apply the policy are on the Clean up tab.
 */
class CleanupBackupPlanTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /** @var array{keep: array<int, list<string>>, prune: Collection<int, BackupRun>}|false|null false = could not be calculated */
    private array|false|null $plan = null;

    public static function canView(): bool
    {
        return CleanupDashboard::canAccess();
    }

    /** @return array{keep: array<int, list<string>>, prune: Collection<int, BackupRun>}|null */
    private function plan(): ?array
    {
        if ($this->plan === null) {
            try {
                $this->plan = app(BackupRetentionService::class)->plan();
            } catch (\Throwable $e) {
                Log::channel('cleanup')->warning('Could not compute the backup retention plan: '.$e->getMessage());
                $this->plan = false;
            }
        }

        return $this->plan === false ? null : $this->plan;
    }

    private function description(): string
    {
        $policy = sprintf(
            '%d daily / %d weekly / %d monthly (set in Backup settings)',
            (int) settings('backup.retention_daily', config('backup.retention.daily', 7)),
            (int) settings('backup.retention_weekly', config('backup.retention.weekly', 4)),
            (int) settings('backup.retention_monthly', config('backup.retention.monthly', 6)),
        );

        $plan = $this->plan();
        if ($plan === null) {
            return 'The retention plan could not be calculated right now (see the cleanup log).';
        }

        $over = $plan['prune']->count();
        $bytes = (int) $plan['prune']->sum(fn ($run) => (int) $run->total_bytes);

        return "Kept by a {$policy} policy. The newest backup of each kind, and any backup an update could still roll back to, are always kept. "
            .count($plan['keep']).' kept, '.$over.' over the policy'.($over > 0 ? ' (~'.CleanupText::bytes($bytes).' would be freed).' : '.');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BackupRun::query()->successful()->whereNull('meta->pruned_at')
                    ->orderByRaw('COALESCE(finished_at, started_at, created_at) DESC')
            )
            ->heading('Backups on disk')
            ->description(fn (): string => $this->description())
            ->defaultPaginationPageOption(12)
            ->paginationPageOptions([12, 25, 50])
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('profile')
                    ->label('Kind'),
                TextColumn::make('taken')
                    ->label('Taken')
                    ->state(fn (BackupRun $record): string => ($record->finished_at ?? $record->created_at)->diffForHumans()),
                TextColumn::make('total_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state): string => CleanupText::bytes((int) $state)),
                TextColumn::make('decision')
                    ->label('Decision')
                    ->badge()
                    ->state(function (BackupRun $record): string {
                        $why = $this->plan()['keep'][$record->id] ?? null;

                        return $why === null ? 'over the policy' : 'keep';
                    })
                    ->color(fn (string $state): string => $state === 'keep' ? 'success' : 'warning')
                    ->description(function (BackupRun $record): ?string {
                        $why = $this->plan()['keep'][$record->id] ?? null;

                        return $why === null ? null : implode(', ', array_map(fn ($w) => match ($w) {
                            'gfs' => 'within the policy',
                            'newest_of_profile' => 'newest of its kind',
                            'update_referenced' => 'an update may roll back to it',
                            'incremental_chain' => 'later backups depend on it',
                            default => $w,
                        }, $why));
                    }),
            ])
            ->headerActions([
                Action::make('manageBackups')
                    ->label('Manage backups')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->url(fn (): string => BackupDashboard::getUrl()),
            ])
            ->emptyStateHeading('No backups on disk')
            ->emptyStateIcon('heroicon-o-archive-box');
    }
}
