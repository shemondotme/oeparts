<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Jobs\RestoreBackupJob;
use App\Models\BackupRun;
use App\Services\Backup\BackupJanitor;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupManager;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Backup Manager (Module 21, Chunk 2.6) — lists Backup Engine runs and drives
 * them: run a new backup, restore, download, delete. Replaces the old file-zip
 * dashboard (Module 14).
 *
 * Access is `manage backups`; download/restore are the most sensitive (a backup
 * is a full PII export, rule #45) so they require password RE-AUTH and are
 * audited to the 'updates' log channel. Custom actions get NO automatic policy
 * enforcement (CLAUDE rule #31), so every mutating action carries an explicit
 * ->authorize().
 */
class BackupDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Backup Management';

    protected string $view = 'filament.pages.system.backup-dashboard';

    protected ?string $subheading = 'Encrypted, chunked backups (database + files). Download and restore are audited PII exports.';

    /** Non-null while an admin-triggered backup is actively being polled to completion. */
    public ?int $runningBackupId = null;

    /** Latest {@see \App\Services\Backup\BackupProgress} snapshot for the running backup, for the progress bar. */
    public array $backupProgress = [];

    /** Human labels for every selectable/stored backup profile. */
    private const PROFILE_LABELS = [
        BackupRun::PROFILE_FULL          => 'Full (database + files)',
        BackupRun::PROFILE_DATABASE_ONLY => 'Database only',
        BackupRun::PROFILE_FILES_ONLY    => 'Files only',
        BackupRun::PROFILE_UPDATE_SAFETY => 'Update safety',
    ];

    public function mount(): void
    {
        // Resume polling a run the operator started before reloading the page —
        // same pattern as SystemUpdates::mount() for the update-apply FSM.
        $running = BackupRun::query()
            ->whereNotIn('status', [BackupRun::STATUS_SUCCESS, BackupRun::STATUS_FAILED])
            ->latest('id')
            ->first();

        if ($running) {
            $this->runningBackupId = $running->id;
        }
    }

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return 25;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('manage backups');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BackupRun::query())
            ->defaultSort('id', 'desc')
            ->poll('15s')
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')->fontMono()->size('sm')->sortable(),

                Tables\Columns\TextColumn::make('profile')
                    ->badge()->size('sm')
                    ->formatStateUsing(fn (string $state): string => self::PROFILE_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        BackupRun::PROFILE_FULL => 'primary',
                        BackupRun::PROFILE_DATABASE_ONLY, BackupRun::PROFILE_FILES_ONLY => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()->size('sm')
                    ->color(fn (string $state): string => match ($state) {
                        BackupRun::STATUS_SUCCESS => 'success',
                        BackupRun::STATUS_FAILED  => 'danger',
                        BackupRun::STATUS_RUNNING => 'warning',
                        default                   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state, BackupRun $record): string => ($record->meta['pruned_at'] ?? null)
                        ? 'pruned' : $state),

                Tables\Columns\TextColumn::make('trigger')->badge()->color('gray')->size('sm'),

                Tables\Columns\IconColumn::make('encrypted')->boolean()->label('Enc'),

                Tables\Columns\TextColumn::make('part_count')->label('Parts')->size('sm'),

                Tables\Columns\TextColumn::make('total_bytes')->label('Size')->size('sm')
                    ->formatStateUsing(fn ($state): string => $this->formatBytes((int) $state)),

                Tables\Columns\TextColumn::make('finished_at')->label('Finished')
                    ->dateTime('M j, H:i')->since()->sortable()->fontMono()->size('sm')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    BackupRun::STATUS_SUCCESS => 'Success',
                    BackupRun::STATUS_FAILED  => 'Failed',
                    BackupRun::STATUS_RUNNING => 'Running',
                ]),
                Tables\Filters\SelectFilter::make('profile')->options(self::PROFILE_LABELS),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    $this->restoreAction(),
                    $this->downloadAction(),
                    $this->deleteAction(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runNow')
                    ->label('Run backup now')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->authorize('manage backups')
                    ->form([
                        Forms\Components\Radio::make('profile')
                            ->label('What should this backup include?')
                            ->options([
                                BackupRun::PROFILE_FULL          => 'Full — database + files (recommended before an update)',
                                BackupRun::PROFILE_DATABASE_ONLY => 'Database only — fastest, smallest',
                                BackupRun::PROFILE_FILES_ONLY    => 'Files only — uploads/config, no database',
                            ])
                            ->descriptions([
                                BackupRun::PROFILE_FULL          => 'Everything needed for full disaster recovery.',
                                BackupRun::PROFILE_DATABASE_ONLY => 'Just the database. Runs in seconds on most sites.',
                                BackupRun::PROFILE_FILES_ONLY    => 'Application files only (vendor/ is excluded — reinstall via composer on restore).',
                            ])
                            ->default(BackupRun::PROFILE_FULL)
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('The backup runs in the background, one chunk per poll — you can leave this page open to watch progress.')
                    ->disabled(fn (): bool => app(BackupLock::class)->isLocked() || $this->runningBackupId !== null)
                    ->action(function (array $data, BackupManager $manager): void {
                        // Start only — advance via pollBackup() below (AJAX-polled, one
                        // chunk per tick). Dispatching RunBackupJob here would run the
                        // WHOLE backup inline under QUEUE_CONNECTION=sync, blocking this
                        // request well past the web server's timeout — exactly the
                        // "stuck on Running" bug this fixes. See BackupManager::run()'s
                        // own doc comment.
                        $profile = (string) ($data['profile'] ?? BackupRun::PROFILE_FULL);

                        try {
                            $run = $manager->start($profile, 'manual');
                        } catch (\Throwable $e) {
                            Notification::make()->title('Backup could not start')->body($e->getMessage())->danger()->send();

                            return;
                        }
                        $this->runningBackupId = $run->id;
                        $this->backupProgress  = [];
                        $this->audit('run', $run);
                        Notification::make()->title('Backup started')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Run a backup to protect your database and files.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    /** Advance the running backup by one chunk per poll tick — never blocks the request. */
    public function pollBackup(BackupManager $manager): void
    {
        if (! $this->runningBackupId) {
            return;
        }

        $run = BackupRun::find($this->runningBackupId);
        if (! $run) {
            $this->runningBackupId = null;

            return;
        }

        if (! $run->isTerminal()) {
            $progress = $manager->advance($run);
            $this->backupProgress = $progress->toArray();
            $run->refresh();
        }

        if ($run->isTerminal()) {
            $this->runningBackupId = null;
            $this->backupProgress  = [];

            if ($run->status === BackupRun::STATUS_SUCCESS) {
                Notification::make()->title('Backup complete')->success()->send();
            } else {
                Notification::make()->title('Backup failed')->body($run->error)->danger()->send();
            }
        }
    }

    private function restoreAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('restore')
            ->label('Restore files')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->authorize('restore backups')
            ->visible(fn (BackupRun $record): bool => $this->isRestorable($record))
            ->form($this->reauthForm('Restoring overwrites files from this backup. Confirm your password.'))
            ->action(function (BackupRun $record, array $data): void {
                $this->reauthenticate($data);
                RestoreBackupJob::dispatch($record->getKey(), ['files' => true, 'database' => false], auth('admin')->id());
                $this->audit('restore', $record);
                Notification::make()->title('Restore queued')
                    ->body('Files will be restored to storage/app/restore/run-'.$record->getKey().'.')
                    ->success()->send();
            });
    }

    private function downloadAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->authorize('manage backups')
            ->visible(fn (BackupRun $record): bool => $this->isRestorable($record) && $this->allPartsLocal($record))
            ->form($this->reauthForm('This downloads an encrypted PII export. Confirm your password.'))
            ->action(function (BackupRun $record, array $data) {
                $this->reauthenticate($data);
                $this->audit('download', $record);

                return $this->streamZip($record);
            });
    }

    private function deleteAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('delete')
            ->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->authorize('manage backups')
            ->requiresConfirmation()
            ->modalDescription('Permanently delete this backup and its files. This cannot be undone.')
            ->action(function (BackupRun $record): void {
                app(BackupJanitor::class)->purgeFiles($record);
                $this->audit('delete', $record);
                $record->delete();
                Notification::make()->title('Backup deleted')->success()->send();
            });
    }

    private function isRestorable(BackupRun $record): bool
    {
        return $record->status === BackupRun::STATUS_SUCCESS && ! ($record->meta['pruned_at'] ?? null);
    }

    private function allPartsLocal(BackupRun $record): bool
    {
        return $record->parts->every(
            fn ($p): bool => config("filesystems.disks.{$p->disk}.driver") === 'local'
        );
    }

    /** @return array<int,\Filament\Forms\Components\Component> */
    private function reauthForm(string $description): array
    {
        return [
            Forms\Components\TextInput::make('password')
                ->label('Confirm your password')
                ->helperText($description)
                ->password()
                ->required(),
        ];
    }

    private function reauthenticate(array $data): void
    {
        $admin = auth('admin')->user();

        if (! $admin || ! Hash::check((string) ($data['password'] ?? ''), $admin->password)) {
            throw ValidationException::withMessages(['password' => 'Your password is incorrect.']);
        }
    }

    private function audit(string $action, ?BackupRun $record): void
    {
        Log::channel(config('updates.log_channel', 'stack'))->notice('backup.'.$action, [
            'admin'   => auth('admin')->id(),
            'run'     => $record?->getKey(),
            'profile' => $record?->profile,
        ]);
    }

    private function streamZip(BackupRun $record): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'oebk').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($record->manifest_path && Storage::disk($record->disk)->exists($record->manifest_path)) {
            $zip->addFromString('manifest.json', Storage::disk($record->disk)->get($record->manifest_path));
        }

        foreach ($record->parts as $part) {
            $abs = Storage::disk($part->disk)->path($part->path);
            if (is_file($abs)) {
                $zip->addFile($abs, ltrim($part->path, '/'));
            }
        }

        $zip->close();

        return response()->download($zipPath, 'oeparts-backup-'.$record->getKey().'.zip')
            ->deleteFileAfterSend(true);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min((int) floor(log($bytes) / log(1024)), count($units) - 1);

        return round($bytes / (1024 ** $pow), $precision).' '.$units[$pow];
    }
}
