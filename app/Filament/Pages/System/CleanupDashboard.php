<?php

namespace App\Filament\Pages\System;

use App\Models\ActivityLog;
use App\Models\BackupRun;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Models\Setting;
use App\Services\Backup\BackupRetentionService;
use App\Services\Cleanup\CleanupAudit;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\CleanupMeasurer;
use App\Services\Cleanup\CleanupOverview;
use App\Services\Cleanup\CleanupText;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Exceptions\CleanupBusyException;
use App\Services\Cleanup\Exceptions\CleanupException;
use App\Services\Cleanup\FileLogManager;
use App\Services\Cleanup\SchemaAuditor;
use App\Services\Cleanup\SystemBusyGuard;
use App\Services\Cleanup\TargetRegistry;
use App\Services\SettingsService;
use App\Services\Updates\GitUpdater;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * System → Cleanup: the admin face of the Cleanup Engine.
 *
 * Everything that deletes goes through {@see CleanupManager}: a run row, a progress bar the page drives by polling
 * (no queue worker needed), a verified result, an audit row and a history entry. The page never touches a table or a
 * path itself, and a request can only name a registered target KEY (an allow-list), never a table or a path.
 *
 * Sections: engine status + disk overview, the active run / last result, log & history cleaning, file logs, backups
 * & updates & other files, Cleanup History, engine settings, and the two original cards (dev-only files, schema
 * audit — report-only).
 *
 * @property-read array<string, mixed> $disk
 * @property-read array<string, array<string, mixed>> $targets
 * @property-read CleanupRun|null $run
 * @property-read Collection<int, CleanupRun> $history
 * @property-read int $historyTotal
 * @property-read list<array{name:string, bytes:int, modified:int, active:bool, deletable:bool, oversized:bool}> $logFiles
 * @property-read array{keep: array<int, list<string>>, prune: Collection<int, \App\Models\BackupRun>}|null $backupPlan
 * @property-read string|null $busyReason
 */
class CleanupDashboard extends Page
{
    protected static ?string $slug = 'system/cleanup-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?string $title = 'Cleanup';

    protected string $view = 'filament.pages.system.cleanup-dashboard';

    protected ?string $subheading = 'See what is using your disk, clean logs, history, backups and leftover files with a preview first, and watch every run be verified. Orders, invoices, payments, customers and products are never touched.';

    public static function getNavigationSort(): ?int
    {
        return 41;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-sparkles';
    }

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('manage cleanup');
    }

    /** The run shown in the run panel: the active one, or the one that just finished. */
    #[Locked]
    public ?int $runId = null;

    /** True while that run is not finished: the panel polls and drives it. */
    #[Locked]
    public bool $running = false;

    public int $historyLimit = 10;

    /** @var list<string> log file basenames ticked in the File Logs section */
    public array $selectedLogs = [];

    /** @var array<string, string> per-target retention inputs (key => days as typed) */
    public array $retentionInputs = [];

    // Engine-wide settings form
    public bool $autoEnabled = false;

    public string $windowStart = '02:00';

    public string $windowEnd = '06:00';

    public int $chunkRows = 2000;

    public int $sleepMs = 50;

    public int $maxDeletePct = 50;

    public int $historyDays = 180;

    public int $diskAlertPct = 80;

    public bool $exportBeforeDelete = false;

    public string $notifyOn = 'partial_failed';

    // The two original, report-oriented cards
    /** @var list<string>|null null = not scanned yet this page load */
    public ?array $devFilesPreview = null;

    public ?string $devFilesScannedAt = null;

    /** @var array{expected_missing: list<string>, unexpected: list<string>}|null */
    public ?array $schemaAudit = null;

    public ?string $schemaAuditedAt = null;

    public function mount(): void
    {
        $this->loadEngineSettings();

        foreach (app(TargetRegistry::class)->all() as $key => $target) {
            if ($target->defaultDays() > 0) {
                $this->retentionInputs[$key] = (string) app(TargetRegistry::class)->retentionDays($target);
            }
        }

        // Pick up a run that is still going (a reload, another tab, a scheduled run) so its progress is never lost.
        $active = CleanupRun::query()->active()->orderByDesc('id')->first();
        if ($active !== null) {
            $this->runId = $active->getKey();
            $this->running = true;
        }

        // Eager, cheap (a handful of is_dir()/is_file() calls) — an operator
        // shouldn't have to click "Scan" just to see whether anything is wrong.
        if (app(GitUpdater::class)->isGitManaged()) {
            $this->scanDevFiles();
        }
    }

    /* ------------------------------------------------------------------ *
     * Data for the view (cached tables only — nothing here counts rows or walks directories)
     * ------------------------------------------------------------------ */

    #[Computed]
    public function disk(): array
    {
        return app(CleanupOverview::class)->disk();
    }

    /** @return array<string, array<string, mixed>> */
    #[Computed]
    public function targets(): array
    {
        return app(CleanupOverview::class)->targets();
    }

    /** @return array<string, array<string, mixed>> */
    public function targetsIn(string ...$groups): array
    {
        return array_filter($this->targets, fn (array $row) => in_array($row['group'], $groups, true));
    }

    #[Computed]
    public function run(): ?CleanupRun
    {
        return $this->runId ? CleanupRun::query()->with('items')->find($this->runId) : null;
    }

    /** @return Collection<int, CleanupRun> */
    #[Computed]
    public function history(): Collection
    {
        return app(CleanupOverview::class)->recentRuns($this->historyLimit);
    }

    #[Computed]
    public function historyTotal(): int
    {
        return CleanupRun::query()->count();
    }

    /** @return list<array{name:string, bytes:int, modified:int, active:bool, deletable:bool, oversized:bool}> */
    #[Computed]
    public function logFiles(): array
    {
        return app(FileLogManager::class)->files();
    }

    /**
     * What backup retention would keep and prune right now, for the Backups section.
     *
     * @return array{keep: array<int, list<string>>, prune: Collection<int, BackupRun>}|null
     */
    #[Computed]
    public function backupPlan(): ?array
    {
        try {
            return app(BackupRetentionService::class)->plan();
        } catch (\Throwable $e) {
            Log::channel('cleanup')->warning('Could not compute the backup retention plan: '.$e->getMessage());

            return null;
        }
    }

    #[Computed]
    public function busyReason(): ?string
    {
        return app(SystemBusyGuard::class)->reason();
    }

    public function isGitManaged(): bool
    {
        return app(GitUpdater::class)->isGitManaged();
    }

    public function text(): CleanupText
    {
        return new CleanupText;
    }

    /* ------------------------------------------------------------------ *
     * Header + modal actions
     * ------------------------------------------------------------------ */

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewAll')
                ->label('Preview everything')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->authorize(fn () => self::canAccess())
                ->action(fn () => $this->startRun($this->allTargetKeys(false), CleanupRun::MODE_DRY_RUN)),
            Action::make('runSchemaAudit')
                ->label('Run Database Audit')
                ->icon('heroicon-o-circle-stack')
                ->color('gray')
                ->action('runSchemaAudit'),
        ];
    }

    /** Clean one target now (typed confirmation when a lot may go). */
    public function cleanAction(): Action
    {
        return Action::make('clean')
            ->label('Clean now')
            ->color('danger')
            ->authorize(fn () => self::canAccess())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Clean: '.$this->labelOf($arguments['target'] ?? ''))
            ->modalDescription(fn (array $arguments) => $this->cleanDescription($arguments))
            ->modalSubmitActionLabel('Yes, clean it')
            ->schema(fn (array $arguments) => $this->needsTypedConfirmation($arguments['target'] ?? '')
                ? [TextInput::make('confirm')->label('Type DELETE to confirm')->required()->in(['DELETE'])->validationMessages(['in' => 'Type DELETE in capital letters to confirm.'])]
                : [])
            ->action(function (array $arguments): void {
                $this->startRun([(string) ($arguments['target'] ?? '')], CleanupRun::MODE_EXECUTE, [
                    'preview_run_id' => isset($arguments['preview']) ? (int) $arguments['preview'] : null,
                ]);
            });
    }

    /**
     * Delete EVERY row of a telemetry log (not only the old ones): typed confirmation AND the admin's password.
     * Named deleteAll, not truncate: this is the engine's chunked row delete, never a TRUNCATE/DROP.
     */
    public function deleteAllAction(): Action
    {
        return Action::make('deleteAll')
            ->label('Delete all')
            ->color('danger')
            ->authorize(fn () => self::canAccess())
            ->modalHeading(fn (array $arguments) => 'Delete ALL of: '.$this->labelOf($arguments['target'] ?? ''))
            ->modalDescription('This removes every row that exists right now, not just the old ones. It cannot be undone. Rows added while it runs are kept.')
            ->modalSubmitActionLabel('Delete everything')
            ->schema([
                TextInput::make('confirm')->label('Type DELETE to confirm')->required()->in(['DELETE'])->validationMessages(['in' => 'Type DELETE in capital letters to confirm.']),
                TextInput::make('password')->label('Confirm your password')->password()->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->reauthenticate($data);
                $this->startRun([(string) ($arguments['target'] ?? '')], CleanupRun::MODE_EXECUTE, ['truncate' => true]);
            });
    }

    public function cleanAllAction(): Action
    {
        return Action::make('cleanAll')
            ->label('Clean everything that is due')
            ->color('danger')
            ->authorize(fn () => self::canAccess())
            ->modalHeading('Clean every enabled target')
            ->modalDescription('Runs the same cleanup the automatic schedule would, for every target that has automatic cleaning switched on, using each target\'s own retention. Preview first if you are unsure.')
            ->modalSubmitActionLabel('Yes, clean everything')
            ->schema([TextInput::make('confirm')->label('Type DELETE to confirm')->required()->in(['DELETE'])->validationMessages(['in' => 'Type DELETE in capital letters to confirm.'])])
            ->action(fn () => $this->startRun($this->allTargetKeys(true), CleanupRun::MODE_EXECUTE));
    }

    public function deleteLogsAction(): Action
    {
        return Action::make('deleteLogs')
            ->label('Delete selected')
            ->color('danger')
            ->authorize(fn () => self::canAccess())
            ->requiresConfirmation()
            ->modalHeading('Delete the selected log files?')
            ->modalDescription(fn () => count($this->selectedLogs).' file(s). A file written to in the last hour cannot be deleted (it may be open); empty it instead.')
            ->action(fn () => $this->applyToLogs('delete'));
    }

    public function emptyLogsAction(): Action
    {
        return Action::make('emptyLogs')
            ->label('Empty selected')
            ->color('warning')
            ->authorize(fn () => self::canAccess())
            ->requiresConfirmation()
            ->modalHeading('Empty the selected log files?')
            ->modalDescription(fn () => count($this->selectedLogs).' file(s) will be emptied in place. The files stay and the application keeps logging into them; the space comes back immediately.')
            ->action(fn () => $this->applyToLogs('truncate'));
    }

    /* ------------------------------------------------------------------ *
     * Running things
     * ------------------------------------------------------------------ */

    public function startPreview(string $target): void
    {
        $this->guard();
        $this->startRun([$target], CleanupRun::MODE_DRY_RUN);
    }

    /**
     * @param  list<string>  $keys
     * @param  array{truncate?:bool, preview_run_id?:int|null}  $options
     */
    private function startRun(array $keys, string $mode, array $options = []): void
    {
        $this->guard();

        $keys = array_values(array_filter($keys, fn ($k) => $k !== ''));

        try {
            // Backups and updates have their own permission on top of this page's.
            foreach ($keys as $key) {
                $target = app(TargetRegistry::class)->get($key);
                if ($target->group() === 'backups' && ! auth('admin')->user()?->can('manage backups')) {
                    Notification::make()->title('Not allowed')->body("Cleaning '{$target->label()}' needs the Manage Backups permission.")->danger()->send();

                    return;
                }
            }

            $run = app(CleanupManager::class)->start($keys, $mode, CleanupRun::TRIGGER_MANUAL, (int) auth('admin')->id(), array_filter($options, fn ($v) => $v !== null && $v !== false));
        } catch (CleanupBusyException $e) {
            Notification::make()->title('Cannot start right now')->body($e->getMessage())->warning()->send();

            return;
        } catch (CleanupException|InvalidArgumentException $e) {
            Notification::make()->title('Cannot start')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->runId = $run->getKey();
        $this->running = true;
        unset($this->run);

        Notification::make()
            ->title($mode === CleanupRun::MODE_DRY_RUN ? 'Preview started' : 'Cleanup started')
            ->body('You can leave this page open; progress is shown below.')
            ->success()
            ->send();
    }

    /** Advance the active run by one slice per poll tick; show the result when it ends. */
    public function pollRun(): void
    {
        $this->guard();

        $run = $this->runId ? CleanupRun::query()->find($this->runId) : null;
        if ($run === null) {
            $this->running = false;

            return;
        }

        if (! $run->isTerminal()) {
            app(CleanupManager::class)->advance($run);
            $run->refresh();
        }

        unset($this->run, $this->targets, $this->disk, $this->history, $this->busyReason, $this->logFiles, $this->backupPlan);

        if ($run->isTerminal()) {
            $this->running = false;
            $this->announceFinished($run);
        }
    }

    public function cancelRun(): void
    {
        $this->guard();

        $run = $this->runId ? CleanupRun::query()->find($this->runId) : null;
        if ($run !== null && ! $run->isTerminal()) {
            app(CleanupManager::class)->cancel($run, (int) auth('admin')->id());
            Notification::make()->title('Stopping…')->body('It will finish the item it is on, check what is left, and then stop.')->send();
        }
    }

    public function resumeRun(): void
    {
        $this->guard();

        $run = $this->runId ? CleanupRun::query()->find($this->runId) : null;
        if ($run === null || ! $run->isTerminal()) {
            return;
        }

        try {
            $new = app(CleanupManager::class)->resumeFrom($run, (int) auth('admin')->id());
        } catch (CleanupBusyException|CleanupException $e) {
            Notification::make()->title('Cannot resume')->body($e->getMessage())->warning()->send();

            return;
        }

        $this->runId = $new->getKey();
        $this->running = true;
        unset($this->run);
    }

    public function dismissRun(): void
    {
        $this->runId = null;
        $this->running = false;
        unset($this->run);
    }

    public function showRun(int $id): void
    {
        $this->guard();

        if (CleanupRun::query()->whereKey($id)->exists()) {
            $this->runId = $id;
            $this->running = ! CleanupRun::query()->find($id)?->isTerminal();
            unset($this->run);
        }
    }

    public function loadMoreHistory(): void
    {
        $this->historyLimit = min(200, $this->historyLimit + 10);
        unset($this->history);
    }

    private function announceFinished(CleanupRun $run): void
    {
        $dry = $run->isDryRun();
        $title = $dry ? 'Preview ready' : CleanupText::status($run->status);

        $notification = Notification::make()->title($title)->body((string) $run->message);

        match (true) {
            $run->status === CleanupRun::STATUS_SUCCESS => $notification->success(),
            $run->status === CleanupRun::STATUS_FAILED => $notification->danger(),
            default => $notification->warning(),
        };

        $notification->send();
    }

    /* ------------------------------------------------------------------ *
     * Settings
     * ------------------------------------------------------------------ */

    public function saveRetention(string $key, mixed $value): void
    {
        $this->guard();

        try {
            $target = app(TargetRegistry::class)->get($key);
        } catch (InvalidArgumentException) {
            return;
        }

        if ($target->defaultDays() <= 0) {
            return; // not an age-based target
        }

        $days = (int) $value;
        $floor = max(1, $target->floorDays());

        if ($days < $floor || $days > 3650) {
            $this->retentionInputs[$key] = (string) app(TargetRegistry::class)->retentionDays($target);
            Notification::make()->title('Not saved')
                ->body("{$target->label()} must be kept between {$floor} and 3650 days"
                    .($target->floorDays() > 0 ? ' (the minimum protects data that is needed for audits or dashboards).' : '.'))
                ->danger()->send();

            return;
        }

        $old = app(TargetRegistry::class)->retentionDays($target);
        $this->saveSetting("retention_{$key}", (string) $days, 'integer');
        $this->retentionInputs[$key] = (string) $days;

        app(CleanupAudit::class)->record('settings_changed', null, ['target' => $key, 'retention_days' => ['from' => $old, 'to' => $days]], (int) auth('admin')->id());
        unset($this->targets);

        Notification::make()->title('Saved')->body("{$target->label()}: kept for {$days} days.")->success()->send();
    }

    public function toggleAuto(string $key): void
    {
        $this->guard();

        try {
            $target = app(TargetRegistry::class)->get($key);
        } catch (InvalidArgumentException) {
            return;
        }

        if ($target->reportOnly()) {
            return;
        }

        $new = ! app(TargetRegistry::class)->autoEnabled($target);
        $this->saveSetting("auto_{$key}", $new ? '1' : '0', 'boolean');

        app(CleanupAudit::class)->record('settings_changed', null, ['target' => $key, 'auto' => $new], (int) auth('admin')->id());
        unset($this->targets);
    }

    public function saveEngineSettings(): void
    {
        $this->guard();

        $this->validate([
            'windowStart' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
            'windowEnd' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
            'chunkRows' => ['required', 'integer', 'min:200', 'max:10000'],
            'sleepMs' => ['required', 'integer', 'min:0', 'max:2000'],
            'maxDeletePct' => ['required', 'integer', 'min:1', 'max:100'],
            'historyDays' => ['required', 'integer', 'min:'.(int) config('cleanup.history_retention_floor_days', 30), 'max:3650'],
            'diskAlertPct' => ['required', 'integer', 'min:50', 'max:99'],
            'notifyOn' => ['required', 'in:partial_failed,failed,never'],
        ], [
            'windowStart.regex' => 'Use a 24-hour time like 02:00.',
            'windowEnd.regex' => 'Use a 24-hour time like 06:00.',
        ]);

        $before = $this->currentEngineSettings();

        $values = [
            'auto_enabled' => [$this->autoEnabled ? '1' : '0', 'boolean'],
            'window_start' => [$this->windowStart, 'string'],
            'window_end' => [$this->windowEnd, 'string'],
            'chunk_rows' => [(string) $this->chunkRows, 'integer'],
            'sleep_ms' => [(string) $this->sleepMs, 'integer'],
            'max_delete_pct' => [(string) $this->maxDeletePct, 'integer'],
            'history_retention_days' => [(string) $this->historyDays, 'integer'],
            'disk_alert_pct' => [(string) $this->diskAlertPct, 'integer'],
            'export_before_delete' => [$this->exportBeforeDelete ? '1' : '0', 'boolean'],
            'notify_on' => [$this->notifyOn, 'string'],
        ];

        foreach ($values as $key => [$value, $type]) {
            $this->saveSetting($key, $value, $type);
        }

        $after = $this->currentEngineSettings();
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }

        if ($changes !== []) {
            app(CleanupAudit::class)->record('settings_changed', null, ['changes' => $changes], (int) auth('admin')->id());
        }

        unset($this->disk, $this->targets);

        Notification::make()->title('Cleanup settings saved')->success()->send();
    }

    /** Persist one setting, making sure the row exists and is not treated as a secret. */
    private function saveSetting(string $key, string $value, string $type): void
    {
        Setting::query()->firstOrCreate(
            ['group' => 'cleanup', 'key' => $key],
            ['value' => $value, 'type' => $type, 'is_encrypted' => false],
        );

        app(SettingsService::class)->set("cleanup.{$key}", $value);
    }

    private function loadEngineSettings(): void
    {
        $cfg = fn (string $key) => settings("cleanup.{$key}", config("cleanup.{$key}"));

        $this->autoEnabled = filter_var($cfg('auto_enabled'), FILTER_VALIDATE_BOOLEAN);
        $this->windowStart = (string) $cfg('window_start');
        $this->windowEnd = (string) $cfg('window_end');
        $this->chunkRows = (int) $cfg('chunk_rows');
        $this->sleepMs = (int) $cfg('sleep_ms');
        $this->maxDeletePct = (int) $cfg('max_delete_pct');
        $this->historyDays = (int) $cfg('history_retention_days');
        $this->diskAlertPct = (int) $cfg('disk_alert_pct');
        $this->exportBeforeDelete = filter_var($cfg('export_before_delete'), FILTER_VALIDATE_BOOLEAN);
        $this->notifyOn = (string) $cfg('notify_on');
    }

    /** @return array<string, mixed> */
    private function currentEngineSettings(): array
    {
        return [
            'auto_enabled' => filter_var(settings('cleanup.auto_enabled', config('cleanup.auto_enabled')), FILTER_VALIDATE_BOOLEAN),
            'window_start' => (string) settings('cleanup.window_start', config('cleanup.window_start')),
            'window_end' => (string) settings('cleanup.window_end', config('cleanup.window_end')),
            'chunk_rows' => (int) settings('cleanup.chunk_rows', config('cleanup.chunk_rows')),
            'sleep_ms' => (int) settings('cleanup.sleep_ms', config('cleanup.sleep_ms')),
            'max_delete_pct' => (int) settings('cleanup.max_delete_pct', config('cleanup.max_delete_pct')),
            'history_retention_days' => (int) settings('cleanup.history_retention_days', config('cleanup.history_retention_days')),
            'disk_alert_pct' => (int) settings('cleanup.disk_alert_pct', config('cleanup.disk_alert_pct')),
            'export_before_delete' => filter_var(settings('cleanup.export_before_delete', config('cleanup.export_before_delete')), FILTER_VALIDATE_BOOLEAN),
            'notify_on' => (string) settings('cleanup.notify_on', config('cleanup.notify_on')),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Numbers, files
     * ------------------------------------------------------------------ */

    /** Re-measure one target (or all) now, with a short time budget; the daily command does the thorough pass. */
    public function refreshStats(?string $key = null): void
    {
        $this->guard();

        $measurer = app(CleanupMeasurer::class);

        try {
            if ($key !== null) {
                $measurer->measureTarget(app(TargetRegistry::class)->get($key), 2.0);
            } else {
                $measurer->measureAll(1.0);
                $measurer->sampleDisk(1.0);
            }
        } catch (InvalidArgumentException) {
            return;
        }

        unset($this->targets, $this->disk);
        Notification::make()->title('Numbers refreshed')->success()->send();
    }

    private function applyToLogs(string $what): void
    {
        $this->guard();

        $manager = app(FileLogManager::class);
        $result = $what === 'delete'
            ? $manager->delete($this->selectedLogs, (int) auth('admin')->id())
            : $manager->truncate($this->selectedLogs, (int) auth('admin')->id());

        $this->selectedLogs = [];
        unset($this->logFiles, $this->disk);

        $verb = $what === 'delete' ? 'Deleted' : 'Emptied';
        $note = Notification::make()
            ->title("{$verb} {$result['deleted']} file(s), freed ".CleanupText::bytes($result['bytes']));

        if ($result['failed'] !== []) {
            $lines = [];
            foreach (array_slice($result['failed'], 0, 5, true) as $name => $reason) {
                $lines[] = "{$name}: ".$this->logReason($reason);
            }
            $note->body(implode("\n", $lines))->warning();
        } else {
            $note->success();
        }

        $note->send();
    }

    private function logReason(string $reason): string
    {
        return match ($reason) {
            'too_new' => 'written to in the last hour — empty it instead of deleting',
            'not_a_log_file' => 'not a log file',
            'symlink' => 'is a link; never touched',
            'missing' => 'already gone',
            default => $reason,
        };
    }

    /* ------------------------------------------------------------------ *
     * The two original cards
     * ------------------------------------------------------------------ */

    public function scanDevFiles(): void
    {
        $this->devFilesPreview = app(GitUpdater::class)->previewDevFilesInWorkingTree();
        $this->devFilesScannedAt = now()->toIso8601String();
    }

    public function removeDevFiles(): void
    {
        abort_unless((bool) auth('admin')->user()?->can('manage cleanup'), 403);

        // The Blade only renders the button on a git-managed install, but a Livewire
        // method is callable directly. On a zip install this would delete real
        // release files (build/, docker/, README.md, ...), so re-check here exactly
        // like the oeparts:update:strip-dev-files command does.
        if (! app(GitUpdater::class)->isGitManaged()) {
            Notification::make()
                ->title('Nothing removed')
                ->body('This install is not git-managed, so there are no dev-only files to strip.')
                ->warning()
                ->send();

            return;
        }

        // Stripping rewrites the working tree; never do that while a backup, update
        // or restore is reading or swapping it.
        if (($busy = app(SystemBusyGuard::class)->reason()) !== null) {
            Notification::make()
                ->title('Not now')
                ->body($busy.' Try again when it has finished.')
                ->warning()
                ->send();

            return;
        }

        $removed = app(GitUpdater::class)->stripDevFilesFromWorkingTree();

        if ($removed !== []) {
            $this->logAction('cleanup_dev_files', 'Removed '.count($removed).' dev-only path(s): '.implode(', ', $removed));
        }

        $this->scanDevFiles(); // refresh — should now report empty

        Notification::make()
            ->title($removed === [] ? 'Nothing to remove' : count($removed).' path(s) removed')
            ->success()
            ->send();
    }

    public function runSchemaAudit(): void
    {
        $this->schemaAudit = app(SchemaAuditor::class)->audit();
        $this->schemaAuditedAt = now()->toIso8601String();

        $findings = count($this->schemaAudit['unexpected']) + count($this->schemaAudit['expected_missing']);

        $this->logAction('schema_audit_run', $findings === 0
            ? 'Database schema audit run: no findings.'
            : 'Database schema audit run: '.$findings.' finding(s) — see the Cleanup dashboard.');

        Notification::make()
            ->title($findings === 0 ? 'No findings' : $findings.' finding(s) — see below')
            ->{$findings === 0 ? 'success' : 'warning'}()
            ->send();
    }

    protected function logAction(string $action, string $description): void
    {
        ActivityLog::create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'model_type' => self::class,
            'model_id' => null,
            'old_values' => [],
            'new_values' => ['description' => $description],
            'ip_address' => request()->ip(),
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    private function guard(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    /** @return list<string> */
    private function allTargetKeys(bool $autoOnly): array
    {
        $registry = app(TargetRegistry::class);

        return array_values(array_map(
            fn (CleanupTarget $t) => $t->key(),
            array_filter($registry->all(), fn (CleanupTarget $t) => ! $t->reportOnly() && (! $autoOnly || $registry->autoEnabled($t))),
        ));
    }

    private function labelOf(string $key): string
    {
        try {
            return app(TargetRegistry::class)->get($key)->label();
        } catch (InvalidArgumentException) {
            return 'unknown target';
        }
    }

    /** A lot may go (or the amount is unknown): make the admin type DELETE. */
    private function needsTypedConfirmation(string $key): bool
    {
        $row = $this->targets[$key] ?? null;
        if ($row === null) {
            return true;
        }

        return $row['eligible_rows'] === null || $row['eligible_rows'] > 10_000;
    }

    /** @param  array<string, mixed>  $arguments */
    private function cleanDescription(array $arguments): string
    {
        $row = $this->targets[$arguments['target'] ?? ''] ?? null;
        if ($row === null) {
            return 'This cannot be undone.';
        }

        $parts = [];
        if ($row['eligible_rows'] !== null) {
            $parts[] = 'About '.CleanupText::number($row['eligible_rows']).' item(s)'.($row['eligible_bytes'] ? ' (~'.CleanupText::bytes($row['eligible_bytes']).')' : '').' qualify right now.';
        } else {
            $parts[] = 'How many items qualify has not been measured yet; a preview shows the exact number.';
        }
        if ($row['age_based']) {
            $parts[] = "Anything older than {$row['days']} days is removed.";
        }
        if (isset($arguments['preview'])) {
            $parts[] = 'This deletes exactly what the preview found.';
        }
        $parts[] = 'It cannot be undone. The result is checked afterwards and reported.';

        return implode(' ', $parts);
    }

    private function reauthenticate(array $data): void
    {
        $admin = auth('admin')->user();

        if (! $admin || ! Hash::check((string) ($data['password'] ?? ''), $admin->password)) {
            throw ValidationException::withMessages(['password' => 'Your password is incorrect.']);
        }
    }

    /** Item rows of a run, ready for the result table. */
    public function itemsOf(CleanupRun $run): Collection
    {
        return $run->items->map(function (CleanupRunItem $item) {
            $label = $this->labelOf($item->target);

            return [
                'item' => $item,
                'label' => $label,
                'status' => CleanupText::status($item->status),
                'tone' => CleanupText::tone($item->status),
                'reasons' => array_map([CleanupText::class, 'reason'], (array) ($item->reasons ?? [])),
            ];
        });
    }
}
