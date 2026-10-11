<?php

namespace App\Filament\Pages\System;

use App\Filament\Widgets\Cleanup\CleanupBackupPlanTable;
use App\Filament\Widgets\Cleanup\CleanupFileLogsTable;
use App\Filament\Widgets\Cleanup\CleanupHistoryTable;
use App\Filament\Widgets\Cleanup\CleanupRunItemsTable;
use App\Filament\Widgets\Cleanup\CleanupStats;
use App\Models\ActivityLog;
use App\Models\CleanupRun;
use App\Models\Setting;
use App\Services\Cleanup\CleanupAudit;
use App\Services\Cleanup\CleanupManager;
use App\Services\Cleanup\CleanupMeasurer;
use App\Services\Cleanup\CleanupOverview;
use App\Services\Cleanup\CleanupProgress;
use App\Services\Cleanup\CleanupText;
use App\Services\Cleanup\Contracts\CleanupTarget;
use App\Services\Cleanup\Exceptions\CleanupBusyException;
use App\Services\Cleanup\Exceptions\CleanupException;
use App\Services\Cleanup\SchemaAuditor;
use App\Services\Cleanup\SystemBusyGuard;
use App\Services\Cleanup\TargetRegistry;
use App\Services\SettingsService;
use App\Services\Updates\GitUpdater;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * System → Cleanup: the admin face of the Cleanup Engine, built only from stock Filament parts (stats widget, callouts,
 * sections, tabs, tables, forms, actions) so it follows the panel's look, dark mode and upgrades with no custom CSS.
 *
 * Everything that deletes goes through {@see CleanupManager}: a run row, progress the page drives by polling (no queue
 * worker needed), a verified result, an audit row and a history entry. The page never touches a table or a path itself, and a
 * request can only name a registered target KEY (an allow-list), never a table or a path.
 *
 * Layout: headline stats, notices, the active run / last result, then tabs — Clean up (every target, with preview / clean /
 * keep-days / automatic), Log files, Backups, History, Settings, Tools (dev-only files, schema audit).
 *
 * @property-read array<string, mixed> $disk
 * @property-read array<string, array<string, mixed>> $targets
 * @property-read CleanupRun|null $run
 * @property-read string|null $busyReason
 */
class CleanupDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'system/cleanup-dashboard';

    /** Target keys that belong to the "Log files" scope of the Clean up table (the folder itself is on the Log files tab). */
    private const FILE_LOG_KEYS = ['file_logs', 'update_logs', 'oversized_logs'];

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?string $title = 'Cleanup';

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

    /** @var array<string, mixed> engine-wide settings form state */
    public array $data = [];

    // The two original, report-oriented tools
    /** @var list<string>|null null = not scanned yet this page load */
    public ?array $devFilesPreview = null;

    public ?string $devFilesScannedAt = null;

    /** @var array{expected_missing: list<string>, unexpected: list<string>}|null */
    public ?array $schemaAudit = null;

    public ?string $schemaAuditedAt = null;

    public function mount(): void
    {
        $this->getSchema('form')->fill($this->currentEngineSettings());

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
     * Data for the page (cached tables only — nothing here counts rows or walks directories)
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

    #[Computed]
    public function run(): ?CleanupRun
    {
        return $this->runId ? CleanupRun::query()->with('items')->find($this->runId) : null;
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

    /* ------------------------------------------------------------------ *
     * Layout
     * ------------------------------------------------------------------ */

    protected function getHeaderWidgets(): array
    {
        return [CleanupStats::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewAll')
                ->label('Preview everything')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->authorize(fn () => self::canAccess())
                ->action(fn () => $this->startRun($this->allTargetKeys(false), CleanupRun::MODE_DRY_RUN)),
            Action::make('refreshNumbers')
                ->label('Refresh numbers')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->authorize(fn () => self::canAccess())
                ->action(fn () => $this->refreshStats()),
            $this->cleanAllAction(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(fn (): string => 'The disk is '.round((float) ($this->disk['used_pct'] ?? 0), 1).'% full')
                ->description('Run a cleanup below, or let the automatic schedule do it overnight.')
                ->icon('heroicon-o-exclamation-triangle')
                ->status(fn (): string => $this->disk['level'] === 'fail' ? 'danger' : 'warning')
                ->visible(fn (): bool => in_array($this->disk['level'], ['warn', 'fail'], true)),

            Callout::make('The system is busy right now')
                ->description(fn (): string => $this->busyReason.' Previews still work; real cleaning waits until it has finished.')
                ->icon('heroicon-o-clock')
                ->info()
                ->visible(fn (): bool => $this->busyReason !== null && ! $this->running),

            $this->runSection(),

            Tabs::make('Cleanup')
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs([
                    Tab::make('Clean up')
                        ->icon('heroicon-o-sparkles')
                        ->schema([EmbeddedTable::make()]),
                    Tab::make('Log files')
                        ->icon('heroicon-o-document-text')
                        ->schema([Livewire::make(CleanupFileLogsTable::class)]),
                    Tab::make('Backups')
                        ->icon('heroicon-o-archive-box')
                        ->schema([Livewire::make(CleanupBackupPlanTable::class)]),
                    Tab::make('History')
                        ->icon('heroicon-o-clock')
                        ->schema([Livewire::make(CleanupHistoryTable::class)]),
                    Tab::make('Settings')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Form::make([EmbeddedSchema::make('form')])
                                ->id('cleanup-settings')
                                ->livewireSubmitHandler('saveEngineSettings')
                                ->footer([
                                    Actions::make([
                                        Action::make('saveSettings')
                                            ->label('Save settings')
                                            ->submit('cleanup-settings'),
                                    ]),
                                ]),
                        ]),
                    Tab::make('Tools')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema($this->toolsSchema()),
                ]),
        ]);
    }

    /** The active run (progress), or the finished one (verified result), as a stock Section. Polls only while it is running. */
    private function runSection(): Section
    {
        $run = fn (): ?CleanupRun => $this->run;
        $terminal = fn (): bool => (bool) $run()?->isTerminal();
        $dry = fn (): bool => (bool) $run()?->isDryRun();

        return Section::make(fn (): string => ($dry() ? 'Preview' : 'Cleanup').' #'.$run()?->id.' — '.($terminal()
            ? CleanupText::status($run()->status, $dry())
            : ($run()?->status === CleanupRun::STATUS_PAUSED ? 'Waiting' : 'Running')))
            ->key('run-section')
            ->icon('heroicon-o-arrow-path')
            ->description(fn (): ?string => $terminal() ? null : ($run()?->message ?: 'Working…'))
            ->visible(fn (): bool => $run() !== null)
            ->extraAttributes(fn (): array => $this->running ? ['wire:poll.2s' => 'pollRun'] : [])
            ->headerActions([
                Action::make('cancelRun')
                    ->label('Cancel')
                    ->icon('heroicon-o-stop')
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn (): bool => ! $terminal() && ! $run()?->isCancelRequested())
                    ->requiresConfirmation()
                    ->modalHeading(fn (): string => 'Stop this '.($dry() ? 'preview' : 'cleanup').'?')
                    ->modalDescription('What was already removed stays removed.')
                    ->action(fn () => $this->cancelRun()),
                Action::make('dismissRun')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->size('sm')
                    ->visible($terminal)
                    ->action(fn () => $this->dismissRun()),
            ])
            ->schema([
                // ── in progress ──
                Flex::make([
                    Text::make(fn (): string => ($run() ? CleanupProgress::fromRun($run())->percent : 0).'%')->size(TextSize::Large)->weight(FontWeight::Bold)->grow(false),
                    Text::make(fn (): string => $this->progressLine()),
                ])->visible(fn (): bool => ! $terminal()),
                Callout::make('Waiting')
                    ->description(fn (): string => $run()?->message.' It continues by itself.')
                    ->icon('heroicon-o-clock')
                    ->warning()
                    ->visible(fn (): bool => $run()?->status === CleanupRun::STATUS_PAUSED),
                Text::make('Safe to leave this page open, or to close it: an unattended run is picked up again within minutes. It can never run at the same time as a backup, update or another cleanup.')
                    ->color('gray')
                    ->size(TextSize::Small)
                    ->visible(fn (): bool => ! $terminal()),

                // ── finished: the verified result ──
                Callout::make(fn (): string => (string) $run()?->message)
                    ->description(fn (): ?string => $this->freedNote())
                    ->status(fn (): string => $dry() ? 'info' : match ($run()?->status) {
                        CleanupRun::STATUS_SUCCESS => 'success',
                        CleanupRun::STATUS_FAILED => 'danger',
                        default => 'warning',
                    })
                    ->visible($terminal),
                Livewire::make(CleanupRunItemsTable::class, fn (): array => ['runId' => (int) $run()?->id])
                    ->key(fn (): string => 'run-items-'.$run()?->id.'-'.$run()?->status)
                    ->visible($terminal),
                Text::make(fn (): string => $this->equation())
                    ->fontFamily(FontFamily::Mono)
                    ->size(TextSize::Small)
                    ->visible(fn (): bool => $terminal() && ! $dry()),
                Text::make(fn (): string => 'Checked after deleting: the same rule was counted again, independently of the delete.'.($this->hasDbSpaceNote()
                    ? ' Space freed inside the database is reusable by the database straight away; the file on disk only shrinks after the table is optimized.'
                    : ''))
                    ->color('gray')
                    ->size(TextSize::Small)
                    ->visible(fn (): bool => $terminal() && ! $dry()),
                Actions::make([
                    $this->deletePreviewedAction(),
                    Action::make('resumeRun')
                        ->label('Resume what is left')
                        ->icon('heroicon-o-play')
                        ->color('primary')
                        ->visible(fn (): bool => $this->hasLeftovers())
                        ->action(fn () => $this->resumeRun()),
                ])->key('run-actions'),
            ]);
    }

    /** One scope of the targets table; the folder of log files has its own tab. */
    private function inScope(array $row, string $scope): bool
    {
        return match ($scope) {
            'all' => true,
            'backups' => $row['group'] === 'backups',
            'files' => $row['group'] === 'files' && ! in_array($row['key'], self::FILE_LOG_KEYS, true),
            'logfiles' => in_array($row['key'], self::FILE_LOG_KEYS, true),
            default => in_array($row['group'], ['logs', 'history', 'system'], true),
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?array $filters): array {
                $scope = $filters['scope']['group'] ?? 'logs';

                return array_filter($this->targets, fn (array $row): bool => $this->inScope($row, (string) $scope));
            })
            ->paginated(false)
            ->heading('What can be cleaned')
            ->description('Sizes are estimates, refreshed daily. Preview first — it deletes nothing. Each target keeps its own number of days; the automatic switch decides whether the schedule cleans it.')
            ->filters([
                Filter::make('scope')
                    ->schema([
                        ToggleButtons::make('group')
                            ->hiddenLabel()
                            ->inline()
                            ->grouped()
                            ->options([
                                'logs' => 'Logs & history',
                                'files' => 'Files & folders',
                                'backups' => 'Backups & updates',
                                'logfiles' => 'Log file rules',
                                'all' => 'Everything',
                            ])
                            ->default('logs'),
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->columns([
                TextColumn::make('label')
                    ->label('What')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (array $record): string => ($record['report_only'] ? 'Report only. ' : '').$record['description'].($record['needs_lock'] ? ' Needs an idle system (no backup or update running).' : ''))
                    ->wrap()
                    ->extraCellAttributes(['style' => 'min-width: 18rem']),
                TextColumn::make('eligible')
                    ->label('Can be cleaned')
                    ->state(fn (array $record): string => $record['eligible_rows'] === null
                        ? 'Not measured'
                        : ($record['eligible_bytes'] ? '~'.CleanupText::bytes($record['eligible_bytes']) : '~'.CleanupText::number($record['eligible_rows']).' items'))
                    ->description(fn (array $record): string => $this->sizeNote($record))
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->extraCellAttributes(['style' => 'min-width: 10rem'])
                    ->color(fn (array $record): string => $record['eligible_rows'] === null ? 'gray' : 'primary'),
                TextColumn::make('last')
                    ->label('Last cleaned')
                    ->state(fn (array $record): string => $record['last_at'] ? CleanupText::status($record['last_status']) : 'Never from here')
                    ->badge()
                    ->wrap()
                    ->visibleFrom('2xl')
                    ->extraCellAttributes(['style' => 'min-width: 10rem'])
                    ->color(fn (array $record): string => $record['last_at'] ? CleanupText::color($record['last_status']) : 'gray')
                    ->description(fn (array $record): ?string => $record['last_at']
                        ? $record['last_at']->diffForHumans().' · '.CleanupText::number($record['last_deleted']).' removed'.($record['last_bytes'] ? ', '.CleanupText::bytes($record['last_bytes']).' freed' : '')
                        : null),
                TextInputColumn::make('days')
                    ->label('Keep (days)')
                    ->type('number')
                    ->placeholder('n/a')
                    ->suffix('days')
                    ->inlineSuffix()
                    ->rules(['numeric'])
                    ->state(fn (array $record): ?int => $record['age_based'] ? $record['days'] : null)
                    ->disabled(fn (array $record): bool => ! $record['age_based'] || $record['report_only'])
                    ->updateStateUsing(fn (array $record, mixed $state): mixed => $this->saveRetention((string) $record['key'], $state)),
                ToggleColumn::make('auto')
                    ->label('Automatic')
                    ->disabled(fn (array $record): bool => $record['report_only'])
                    ->updateStateUsing(fn (array $record, mixed $state): bool => $this->saveAuto((string) $record['key'], (bool) $state)),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->disabled(fn (): bool => $this->running)
                    ->action(fn (array $record) => $this->startPreview((string) $record['key'])),
                Action::make('clean')
                    ->label('Clean now')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (array $record): bool => ! $record['report_only'])
                    ->disabled(fn (): bool => $this->running)
                    ->authorize(fn () => self::canAccess())
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => 'Clean: '.$record['label'])
                    ->modalDescription(fn (array $record): string => $this->cleanDescription((string) $record['key']))
                    ->modalSubmitActionLabel('Yes, clean it')
                    ->schema(fn (array $record): array => $this->confirmSchema((string) $record['key']))
                    ->action(fn (array $record) => $this->startRun([(string) $record['key']], CleanupRun::MODE_EXECUTE)),
                ActionGroup::make([
                    $this->deleteAllRecordAction(),
                    Action::make('remeasure')
                        ->label('Re-measure')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn (array $record) => $this->refreshStats((string) $record['key'])),
                ])->icon('heroicon-m-ellipsis-vertical')->iconButton(),
            ])
            ->emptyStateHeading('Nothing in this group')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    /** "276 rows qualify · of 160 KB · oldest 2026-09-22" under the eligible amount. */
    private function sizeNote(array $row): string
    {
        $unit = $row['kind'] === 'db' ? 'rows' : 'items';
        $parts = [];

        if ($row['eligible_rows'] !== null) {
            $parts[] = CleanupText::number($row['eligible_rows']).' '.$unit.' qualify';
        }

        if ($row['bytes'] !== null) {
            $parts[] = 'of '.CleanupText::bytes($row['bytes']).($row['kind'] === 'db' && $row['rows'] !== null ? ' · ~'.CleanupText::number($row['rows']).' rows' : '');
        }

        if ($row['kind'] === 'db' && $row['oldest']) {
            $parts[] = 'oldest '.$row['oldest']->format('Y-m-d');
        }

        return implode(' · ', $parts);
    }

    /* ------------------------------------------------------------------ *
     * Settings form
     * ------------------------------------------------------------------ */

    public function form(Schema $schema): Schema
    {
        $time = ['regex' => 'Use a 24-hour time like 02:00.'];
        $timeRule = '/^([01]?\d|2[0-3]):[0-5]\d$/';

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Automatic cleaning & safety settings')
                    ->description('When automatic cleaning is on, every target whose "Automatic" switch is on is cleaned about once a day, only inside the time window below, and only while no backup or update is running. The first automatic run of a target is always a preview, so nothing is deleted on the first night. A target that would delete more of its table than the safety limit allows is skipped and you are notified.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Toggle::make('auto_enabled')
                            ->label('Automatic cleaning')
                            ->helperText("Needs the server's scheduler (cron) to be running."),
                        Select::make('notify_on')
                            ->label('Tell admins about automatic runs that…')
                            ->options(['partial_failed' => 'did not finish cleanly', 'failed' => 'failed', 'never' => "never (don't notify)"])
                            ->native(false)
                            ->required(),
                        TextInput::make('window_start')
                            ->label('Allowed from (time)')
                            ->placeholder('02:00')
                            ->required()
                            ->regex($timeRule)
                            ->validationMessages($time),
                        TextInput::make('window_end')
                            ->label('Until (time)')
                            ->placeholder('06:00')
                            ->required()
                            ->regex($timeRule)
                            ->validationMessages($time)
                            ->helperText("Your store's local time. The nightly backup runs at ".settings('backup.schedule_time', config('backup.schedule.time', '01:00')).'.'),
                        TextInput::make('max_delete_pct')
                            ->label('Safety limit for automatic runs (%)')
                            ->numeric()->integer()->minValue(1)->maxValue(100)->required()
                            ->helperText('An automatic run skips a table when it would delete more than this share of it.'),
                        TextInput::make('chunk_rows')
                            ->label('Rows per step')
                            ->numeric()->integer()->minValue(200)->maxValue(10000)->required()
                            ->helperText('Smaller is gentler on a busy database. It adapts by itself within this ceiling.'),
                        TextInput::make('sleep_ms')
                            ->label('Pause between steps (ms)')
                            ->numeric()->integer()->minValue(0)->maxValue(2000)->required(),
                        TextInput::make('history_retention_days')
                            ->label('Keep cleanup history (days)')
                            ->numeric()->integer()->minValue((int) config('cleanup.history_retention_floor_days', 30))->maxValue(3650)->required(),
                        TextInput::make('disk_alert_pct')
                            ->label('Warn when the disk is this full (%)')
                            ->numeric()->integer()->minValue(50)->maxValue(99)->required(),
                        Toggle::make('export_before_delete')
                            ->label('Export rows before deleting')
                            ->helperText('Saves a compressed CSV of what is removed (kept 30 days). It can contain personal data; large tables are skipped rather than exported.'),
                    ]),
            ]);
    }

    public function saveEngineSettings(): void
    {
        $this->guard();

        $state = $this->getSchema('form')->getState();

        $before = $this->currentEngineSettings();

        $values = [
            'auto_enabled' => [! empty($state['auto_enabled']) ? '1' : '0', 'boolean'],
            'window_start' => [(string) $state['window_start'], 'string'],
            'window_end' => [(string) $state['window_end'], 'string'],
            'chunk_rows' => [(string) (int) $state['chunk_rows'], 'integer'],
            'sleep_ms' => [(string) (int) $state['sleep_ms'], 'integer'],
            'max_delete_pct' => [(string) (int) $state['max_delete_pct'], 'integer'],
            'history_retention_days' => [(string) (int) $state['history_retention_days'], 'integer'],
            'disk_alert_pct' => [(string) (int) $state['disk_alert_pct'], 'integer'],
            'export_before_delete' => [! empty($state['export_before_delete']) ? '1' : '0', 'boolean'],
            'notify_on' => [(string) $state['notify_on'], 'string'],
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
        $this->dispatch('cleanup-changed');

        Notification::make()->title('Cleanup settings saved')->success()->send();
    }

    /* ------------------------------------------------------------------ *
     * Header + row actions
     * ------------------------------------------------------------------ */

    public function cleanAllAction(): Action
    {
        return Action::make('cleanAll')
            ->label('Clean everything that is due')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (): bool => ! $this->running)
            ->authorize(fn () => self::canAccess())
            ->modalHeading('Clean every enabled target')
            ->modalDescription('Runs the same cleanup the automatic schedule would, for every target that has automatic cleaning switched on, using each target\'s own retention. Preview first if you are unsure.')
            ->modalSubmitActionLabel('Yes, clean everything')
            ->schema([$this->typeDelete()])
            ->action(fn () => $this->startRun($this->allTargetKeys(true), CleanupRun::MODE_EXECUTE));
    }

    /**
     * "Delete these N now" after a preview: deletes exactly what the preview found (the engine reuses its cutoff).
     */
    public function deletePreviewedAction(): Action
    {
        return Action::make('deletePreviewed')
            ->label(fn (): string => 'Delete these '.CleanupText::number($this->run?->targeted).' now')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (): bool => $this->previewTarget() !== null)
            ->authorize(fn () => self::canAccess())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => 'Clean: '.$this->labelOf((string) $this->previewTarget()))
            ->modalDescription(fn (): string => $this->cleanDescription((string) $this->previewTarget(), true))
            ->modalSubmitActionLabel('Yes, clean it')
            ->schema(fn (): array => $this->confirmSchema((string) $this->previewTarget()))
            ->action(function (): void {
                $run = $this->run;
                $target = $this->previewTarget();
                if ($run === null || $target === null) {
                    return;
                }

                $this->startRun([$target], CleanupRun::MODE_EXECUTE, ['preview_run_id' => $run->getKey()]);
            });
    }

    /**
     * Delete EVERY row of a telemetry log (not only the old ones): typed confirmation AND the admin's password.
     * Named deleteAll, not truncate: this is the engine's chunked row delete, never a TRUNCATE/DROP.
     */
    private function deleteAllRecordAction(): Action
    {
        return Action::make('deleteAll')
            ->label('Delete all')
            ->icon('heroicon-o-fire')
            ->color('danger')
            ->visible(fn (array $record): bool => $record['truncatable'] && ! $record['report_only'])
            ->disabled(fn (): bool => $this->running)
            ->authorize(fn () => self::canAccess())
            ->modalHeading(fn (array $record): string => 'Delete ALL of: '.$record['label'])
            ->modalDescription('This removes every row that exists right now, not just the old ones. It cannot be undone. Rows added while it runs are kept.')
            ->modalSubmitActionLabel('Delete everything')
            ->schema([
                $this->typeDelete(),
                TextInput::make('password')->label('Confirm your password')->password()->required(),
            ])
            ->action(function (array $data, array $record): void {
                $this->reauthenticate($data);
                $this->startRun([(string) $record['key']], CleanupRun::MODE_EXECUTE, ['truncate' => true]);
            });
    }

    private function typeDelete(): TextInput
    {
        return TextInput::make('confirm')
            ->label('Type DELETE to confirm')
            ->required()
            ->in(['DELETE'])
            ->validationMessages(['in' => 'Type DELETE in capital letters to confirm.']);
    }

    /** @return list<TextInput> */
    private function confirmSchema(string $key): array
    {
        return $this->needsTypedConfirmation($key) ? [$this->typeDelete()] : [];
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
        $this->dispatch('cleanup-changed');

        Notification::make()
            ->title($mode === CleanupRun::MODE_DRY_RUN ? 'Preview started' : 'Cleanup started')
            ->body('You can leave this page open; progress is shown above.')
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

        unset($this->run, $this->targets, $this->disk, $this->busyReason);

        if ($run->isTerminal()) {
            $this->running = false;
            $this->announceFinished($run);
            $this->dispatch('cleanup-changed');
        }
    }

    public function cancelRun(): void
    {
        $this->guard();

        $run = $this->runId ? CleanupRun::query()->find($this->runId) : null;
        if ($run !== null && ! $run->isTerminal()) {
            app(CleanupManager::class)->cancel($run, (int) auth('admin')->id());
            unset($this->run);
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
        $this->dispatch('cleanup-changed');
    }

    public function dismissRun(): void
    {
        $this->runId = null;
        $this->running = false;
        unset($this->run);
    }

    /** Opened from the History tab's "Details" button (a separate component, so it arrives as an event). */
    #[On('cleanup-show-run')]
    public function showRun(int $id): void
    {
        $this->guard();

        if (CleanupRun::query()->whereKey($id)->exists()) {
            $this->runId = $id;
            $this->running = ! CleanupRun::query()->find($id)?->isTerminal();
            unset($this->run);
        }
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

    /** "Step Delete · Found 450 · Removed 200 · Elapsed 4s · About 5s left" while a run is going. */
    private function progressLine(): string
    {
        $run = $this->run;
        if ($run === null) {
            return '';
        }

        $progress = CleanupProgress::fromRun($run);

        return 'Step '.ucfirst((string) $run->step)
            .' · Found '.CleanupText::number($run->targeted)
            .' · Removed '.CleanupText::number($run->deleted)
            .' · Elapsed '.CleanupText::duration($progress->elapsedSeconds)
            .($progress->etaSeconds !== null ? ' · About '.CleanupText::duration($progress->etaSeconds).' left' : '');
    }

    /** What the run section says under the result message. */
    private function freedNote(): ?string
    {
        $run = $this->run;
        if ($run === null) {
            return null;
        }

        if ($run->isDryRun()) {
            $bytes = (int) $run->items->sum(fn ($item) => (int) $item->bytes_freed_apparent);

            return $bytes > 0 ? 'Would free about '.CleanupText::bytes($bytes).'.' : null;
        }

        return $run->bytes_freed !== null ? 'Freed about '.CleanupText::bytes($run->bytes_freed).'.' : null;
    }

    /** "Found X = removed Y + gone elsewhere Z + left W": the verification identity, in words. */
    private function equation(): string
    {
        $run = $this->run;
        if ($run === null) {
            return '';
        }

        $text = 'Found '.CleanupText::number($run->targeted)
            .' = removed '.CleanupText::number($run->deleted)
            .' + gone elsewhere '.CleanupText::number((int) $run->items->sum('external_gone'))
            .' + left '.CleanupText::number($run->remaining);

        if ((int) $run->items->whereIn('status', ['skipped_guard', 'skipped_floor', 'blocked_busy'])->sum('targeted') > 0) {
            $text .= ' (left includes what was skipped)';
        }

        return $text;
    }

    private function hasDbSpaceNote(): bool
    {
        $run = $this->run;

        return $run !== null && $run->items->contains(
            fn ($item) => $item->bytes_freed_apparent !== null && $item->bytes_freed_allocated === null && ($this->targets[$item->target]['kind'] ?? null) === 'db'
        );
    }

    private function hasLeftovers(): bool
    {
        $run = $this->run;

        return $run !== null && $run->isTerminal() && ! $run->isDryRun()
            && in_array($run->status, [CleanupRun::STATUS_PARTIAL, CleanupRun::STATUS_FAILED, CleanupRun::STATUS_CANCELLED], true)
            && $run->items->contains(fn ($item) => $item->status !== 'success');
    }

    /** The single target of a finished, successful preview that found something and may be deleted for real. */
    private function previewTarget(): ?string
    {
        $run = $this->run;
        if ($run === null || ! $run->isDryRun() || ! $run->isTerminal() || $run->status !== CleanupRun::STATUS_SUCCESS
            || $run->kind !== CleanupRun::KIND_SINGLE || $run->targeted <= 0) {
            return null;
        }

        $row = $this->targets[$run->target] ?? null;

        return $row !== null && ! $row['report_only'] ? (string) $run->target : null;
    }

    /* ------------------------------------------------------------------ *
     * Settings: retention and the automatic switch per target
     * ------------------------------------------------------------------ */

    /** Returns the value that is stored afterwards (the old one when the new one is refused). */
    public function saveRetention(string $key, mixed $value): ?int
    {
        $this->guard();

        try {
            $target = app(TargetRegistry::class)->get($key);
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($target->defaultDays() <= 0) {
            return null; // not an age-based target
        }

        $current = app(TargetRegistry::class)->retentionDays($target);
        $days = (int) $value;
        $floor = max(1, $target->floorDays());

        if (! is_numeric($value) || $days < $floor || $days > 3650) {
            Notification::make()->title('Not saved')
                ->body("{$target->label()} must be kept between {$floor} and 3650 days"
                    .($target->floorDays() > 0 ? ' (the minimum protects data that is needed for audits or dashboards).' : '.'))
                ->danger()->send();

            return $current;
        }

        $this->saveSetting("retention_{$key}", (string) $days, 'integer');

        app(CleanupAudit::class)->record('settings_changed', null, ['target' => $key, 'retention_days' => ['from' => $current, 'to' => $days]], (int) auth('admin')->id());
        unset($this->targets);

        Notification::make()->title('Saved')->body("{$target->label()}: kept for {$days} days.")->success()->send();

        return $days;
    }

    public function saveAuto(string $key, bool $on): bool
    {
        $this->guard();

        try {
            $target = app(TargetRegistry::class)->get($key);
        } catch (InvalidArgumentException) {
            return false;
        }

        if ($target->reportOnly()) {
            return false;
        }

        $this->saveSetting("auto_{$key}", $on ? '1' : '0', 'boolean');

        app(CleanupAudit::class)->record('settings_changed', null, ['target' => $key, 'auto' => $on], (int) auth('admin')->id());
        unset($this->targets);

        return $on;
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
     * Numbers
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
        $this->dispatch('cleanup-changed');
        Notification::make()->title('Numbers refreshed')->success()->send();
    }

    /* ------------------------------------------------------------------ *
     * Tools tab: the two original report-oriented cards
     * ------------------------------------------------------------------ */

    /** @return list<Section> */
    private function toolsSchema(): array
    {
        return [
            Section::make('Dev-only files')
                ->description('Files a release never intends to ship on a live install (tests/, phpstan*.neon, compose.yaml, docker/, …) — normally stripped automatically as the last step of every update. A request killed from outside PHP partway through an update can leave some of these behind. .env, storage/ and .git are never touched by this.')
                ->headerActions([
                    Action::make('scanDevFiles')
                        ->label('Scan')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->visible(fn (): bool => $this->isGitManaged())
                        ->action(fn () => $this->scanDevFiles()),
                    Action::make('removeDevFiles')
                        ->label('Remove now')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (): bool => $this->isGitManaged() && ($this->devFilesPreview ?? []) !== [])
                        ->requiresConfirmation()
                        ->modalHeading('Remove the dev-only files?')
                        ->modalDescription(fn (): string => 'Remove these '.count($this->devFilesPreview ?? []).' dev-only path(s) from the live install? This only touches the exact paths listed.')
                        ->action(fn () => $this->removeDevFiles()),
                ])
                ->schema([
                    Callout::make('This install is not git-managed')
                        ->description('There is no .git directory — a release zip never ships these files in the first place, so there is nothing to find here.')
                        ->icon('heroicon-o-information-circle')
                        ->info()
                        ->visible(fn (): bool => ! $this->isGitManaged()),
                    Text::make('Click Scan to check.')->color('gray')
                        ->visible(fn (): bool => $this->isGitManaged() && $this->devFilesPreview === null),
                    Callout::make('Clean — nothing to remove.')
                        ->icon('heroicon-o-check-circle')
                        ->success()
                        ->visible(fn (): bool => $this->isGitManaged() && $this->devFilesPreview === []),
                    Callout::make(fn (): string => count($this->devFilesPreview ?? []).' path(s) found')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->warning()
                        ->visible(fn (): bool => ($this->devFilesPreview ?? []) !== []),
                    UnorderedList::make(fn (): array => array_map(fn (string $path) => Text::make($path)->fontFamily(FontFamily::Mono), $this->devFilesPreview ?? []))
                        ->visible(fn (): bool => ($this->devFilesPreview ?? []) !== []),
                    Text::make(fn (): string => 'Last scanned '.Carbon::parse($this->devFilesScannedAt)->diffForHumans())
                        ->color('gray')->size(TextSize::Small)
                        ->visible(fn (): bool => $this->isGitManaged() && $this->devFilesScannedAt !== null),
                ]),

            Section::make('Database schema audit')
                ->description('Compares the live database against what this install\'s own migration history says should exist. Report only — nothing here ever deletes a table or a row. Migrations only ever add, so this should come back clean; anything listed is worth a human look, not something to act on automatically.')
                ->headerActions([
                    Action::make('runSchemaAudit')
                        ->label('Run Database Audit')
                        ->icon('heroicon-o-circle-stack')
                        ->color('gray')
                        ->action(fn () => $this->runSchemaAudit()),
                ])
                ->schema([
                    Text::make('Click "Run Database Audit" to check.')->color('gray')
                        ->visible(fn (): bool => $this->schemaAudit === null),
                    Callout::make('Clean — the live database exactly matches the migration history.')
                        ->icon('heroicon-o-check-circle')
                        ->success()
                        ->visible(fn (): bool => $this->schemaAudit !== null && $this->auditFindings() === 0),
                    Callout::make(fn (): string => count($this->schemaAudit['unexpected'] ?? []).' table(s) not accounted for by any migration')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->warning()
                        ->visible(fn (): bool => ($this->schemaAudit['unexpected'] ?? []) !== []),
                    UnorderedList::make(fn (): array => array_map(fn (string $t) => Text::make($t)->fontFamily(FontFamily::Mono), $this->schemaAudit['unexpected'] ?? []))
                        ->visible(fn (): bool => ($this->schemaAudit['unexpected'] ?? []) !== []),
                    Callout::make(fn (): string => count($this->schemaAudit['expected_missing'] ?? []).' table(s) a migration created but that no longer exist')
                        ->icon('heroicon-o-x-circle')
                        ->danger()
                        ->visible(fn (): bool => ($this->schemaAudit['expected_missing'] ?? []) !== []),
                    UnorderedList::make(fn (): array => array_map(fn (string $t) => Text::make($t)->fontFamily(FontFamily::Mono), $this->schemaAudit['expected_missing'] ?? []))
                        ->visible(fn (): bool => ($this->schemaAudit['expected_missing'] ?? []) !== []),
                    Text::make(fn (): string => 'Last audited '.Carbon::parse($this->schemaAuditedAt)->diffForHumans())
                        ->color('gray')->size(TextSize::Small)
                        ->visible(fn (): bool => $this->schemaAuditedAt !== null),
                ]),

            Section::make('Scheduled maintenance tasks')
                ->description('Expired OTPs, abandoned carts, cached invoices, long-resolved refund images and abandoned backup/update runs are cleaned automatically on their own schedule. To run any of them on demand instead of waiting:')
                ->footerActions([
                    Action::make('openScheduledTasks')
                        ->label('Open Scheduled Tasks')
                        ->icon('heroicon-o-clock')
                        ->url(fn (): string => ScheduledTasksPage::getUrl()),
                ]),
        ];
    }

    private function auditFindings(): int
    {
        return count($this->schemaAudit['unexpected'] ?? []) + count($this->schemaAudit['expected_missing'] ?? []);
    }

    public function scanDevFiles(): void
    {
        $this->devFilesPreview = app(GitUpdater::class)->previewDevFilesInWorkingTree();
        $this->devFilesScannedAt = now()->toIso8601String();
    }

    public function removeDevFiles(): void
    {
        abort_unless((bool) auth('admin')->user()?->can('manage cleanup'), 403);

        // The page only offers the button on a git-managed install, but a Livewire
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

        $findings = $this->auditFindings();

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

    private function cleanDescription(string $key, bool $fromPreview = false): string
    {
        $row = $this->targets[$key] ?? null;
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
        if ($fromPreview) {
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
}
