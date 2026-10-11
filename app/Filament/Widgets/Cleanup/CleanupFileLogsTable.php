<?php

namespace App\Filament\Widgets\Cleanup;

use App\Filament\Pages\System\CleanupDashboard;
use App\Filament\Pages\System\LogViewerPage;
use App\Services\Cleanup\CleanupText;
use App\Services\Cleanup\FileLogManager;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * storage/logs: pick files to delete, or to empty in place. A file written to in the last hour is "active" and can only be
 * emptied (the application keeps logging into it). All checks stay in {@see FileLogManager}; this only chooses names.
 */
class CleanupFileLogsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CleanupDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        $files = app(FileLogManager::class)->files();
        $total = CleanupText::bytes((int) array_sum(array_column($files, 'bytes')));

        return $table
            ->records(fn (): array => collect(app(FileLogManager::class)->files())->keyBy('name')->all())
            ->heading('Log files')
            ->description(count($files).' file(s), '.$total.'. Tick files to delete them or to empty them in place. A file written to in the last hour is "active": it can only be emptied.')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('File')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('bytes')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => CleanupText::bytes($state)),
                TextColumn::make('modified')
                    ->label('Last written')
                    ->formatStateUsing(fn (int $state): string => Carbon::createFromTimestamp($state)->diffForHumans()),
                TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->state(fn (array $record): string => $record['active'] ? 'active' : ($record['oversized'] ? 'over 50 MB' : 'idle'))
                    ->color(fn (array $record): string => $record['active'] ? 'info' : ($record['oversized'] ? 'warning' : 'gray')),
            ])
            ->headerActions([
                Action::make('logViewer')
                    ->label('Open Log Viewer')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (): string => LogViewerPage::getUrl())
                    ->visible(fn (): bool => (bool) auth('admin')->user()?->hasRole('super_admin')),
            ])
            ->toolbarActions([
                BulkAction::make('deleteLogs')
                    ->label('Delete selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete the selected log files?')
                    ->modalDescription('A file written to in the last hour cannot be deleted (it may be open); empty it instead.')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $this->apply('delete', $records)),
                BulkAction::make('emptyLogs')
                    ->label('Empty selected')
                    ->icon('heroicon-o-archive-box-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Empty the selected log files?')
                    ->modalDescription('The files stay and the application keeps logging into them; the space comes back immediately.')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $this->apply('truncate', $records)),
            ])
            ->emptyStateHeading('No log files')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /** @param  Collection<int|string, array<string, mixed>>  $records */
    private function apply(string $what, Collection $records): void
    {
        abort_unless(CleanupDashboard::canAccess(), 403);

        $names = $records->pluck('name')->map(fn ($n) => (string) $n)->values()->all();
        $manager = app(FileLogManager::class);
        $adminId = (int) auth('admin')->id();
        $result = $what === 'delete' ? $manager->delete($names, $adminId) : $manager->truncate($names, $adminId);

        $note = Notification::make()
            ->title(($what === 'delete' ? 'Deleted ' : 'Emptied ').$result['deleted'].' file(s), freed '.CleanupText::bytes($result['bytes']));

        if ($result['failed'] !== []) {
            $lines = [];
            foreach (array_slice($result['failed'], 0, 5, true) as $name => $reason) {
                $lines[] = $name.': '.$this->reason($reason);
            }
            $note->body(implode("\n", $lines))->warning();
        } else {
            $note->success();
        }

        $note->send();
        $this->dispatch('cleanup-changed');
    }

    private function reason(string $reason): string
    {
        return match ($reason) {
            'too_new' => 'written to in the last hour — empty it instead of deleting',
            'not_a_log_file' => 'not a log file',
            'symlink' => 'is a link; never touched',
            'missing' => 'already gone',
            default => $reason,
        };
    }
}
