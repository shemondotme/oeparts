<?php

namespace App\Filament\Pages\System\Concerns;

use App\Filament\Widgets\Migration\Concerns\ConfirmsPassword;
use App\Filament\Widgets\Migration\GroupPickerSchema;
use App\Filament\Widgets\Migration\WizardSubmit;
use App\Models\BackupRun;
use App\Security\PackageAudit;
use App\Services\Backup\BackupProgress;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportManager;
use App\Services\Migration\Export\ExportOptions;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\ExportPlanner;
use App\Services\Migration\Export\FileCounter;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\Export\ProgressPresenter;
use App\Services\Migration\Export\Transform\PiiMap;
use App\Services\Migration\Groups\FileRoot;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationProgress;
use App\Services\Migration\MigrationQueues;
use App\Services\Migration\Package\PassphraseInput;
use App\Services\Migration\Ux\MigrationAccess;
use App\Services\Migration\Ux\MigrationText;
use App\Services\Migration\Ux\PassphraseGuidance;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * The Export tab of System → Migration: a five-step wizard (what, which parts, how, protect it, review and start) and the run
 * panel that follows a started export. Everything it decides goes through the same planner and manager as the command line.
 *
 * A freeze export is never driven from this page: a browser tab cannot be trusted to release the shop. It is started for a
 * queue worker that has proven it serves the migration queue (or refused with the exact command to run instead), and the panel
 * only shows it. A live-copy export is driven by the page's own poll while it is open.
 *
 * Starting needs the admin's password again and is limited to `migration.export.starts_per_hour` (3) starts an hour for the
 * whole site, so a hijacked session cannot make a stream of full copies of the shop.
 */
trait HasExportWizard
{
    use ConfirmsPassword;

    /** The cache key of the starts-per-hour limiter: one for the whole site, because every export is a full copy of it. */
    private const EXPORT_STARTS_KEY = 'migration:export-starts';

    /** @var array<string, mixed> */
    public array $exportData = [];

    #[Locked]
    public ?int $exportRunId = null;

    /**
     * The number and size of the files of the chosen parts, counted once when the person reaches the review step (a count walks the
     * disk, so it is never repeated on every redraw). Ignored when the choice changed since: {key, count, bytes, complete}.
     *
     * @var array<string, mixed>|null
     */
    #[Locked]
    public ?array $exportFileCount = null;

    /** @var array<string, ExportPlan> plans already made during this request, by the choices they were made from */
    private array $exportPlans = [];

    public function mountHasExportWizard(): void
    {
        $this->getSchema('exportForm')->fill($this->exportDefaults());

        $active = BackupRun::query()->exports()->whereIn('status', [BackupRun::STATUS_PENDING, BackupRun::STATUS_RUNNING])->orderByDesc('id')->first();
        $this->exportRunId = $active?->getKey();
    }

    /** @return array<string, mixed> */
    private function exportDefaults(): array
    {
        return [
            'preset' => Preset::FullLive->value,
            'groups' => array_map(static fn (GroupKey $k): string => $k->value, Preset::FullLive->groups()),
            'consistency' => ExportOptions::LIVE,
            'hold' => true,
            'hold_minutes' => null,
            'include_secrets' => false,
            'passphrase' => null,
            'passphrase_confirmation' => null,
            'password' => null,
        ];
    }

    public function exportForm(Schema $schema): Schema
    {
        $presetLabels = ['custom' => __('migration.ui.export.custom_label')];
        $presetDescriptions = ['custom' => __('migration.ui.export.custom_text')];

        foreach (Preset::cases() as $preset) {
            $presetLabels[$preset->value] = __($preset->labelKey());
            $presetDescriptions[$preset->value] = __($preset->descriptionKey());
        }

        return $schema->statePath('exportData')->components([
            Wizard::make([
                Step::make(__('migration.ui.export.step_what'))
                    ->schema([
                        Radio::make('preset')
                            ->label(__('migration.ui.export.start_from'))
                            ->options($presetLabels)
                            ->descriptions($presetDescriptions)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, $set): void {
                                $preset = $state === null ? null : Preset::tryFrom($state);

                                if ($preset !== null) {
                                    $set('groups', array_map(static fn (GroupKey $k): string => $k->value, $preset->groups()));
                                }

                                if ($state === Preset::Staging->value) {
                                    $set('include_secrets', false);
                                }
                            }),
                        $this->notScrubbedSection('not-scrubbed-what'),
                    ]),
                Step::make(__('migration.ui.export.step_parts'))
                    ->schema([
                        GroupPickerSchema::make('groups')
                            ->label(__('migration.ui.export.parts'))
                            ->disabled(fn (): bool => ($this->exportData['preset'] ?? 'custom') !== 'custom')
                            ->live()
                            ->afterStateUpdated(fn () => $this->announceAdded())
                            ->helperText(__('migration.ui.export.parts_help')),
                        Text::make(fn (): string => $this->addedText())->visible(fn (): bool => $this->addedText() !== ''),
                        $this->noticesSection('notices-parts'),
                    ]),
                Step::make(__('migration.ui.export.step_how'))
                    ->schema([
                        Radio::make('consistency')
                            ->label(__('migration.ui.export.while_copying'))
                            ->options([
                                ExportOptions::LIVE => __('migration.ui.export.live_label'),
                                ExportOptions::FREEZE => __('migration.ui.export.freeze_label'),
                            ])
                            ->descriptions([
                                ExportOptions::LIVE => __('migration.ui.export.live_text'),
                                ExportOptions::FREEZE => $this->freezeDescription(),
                            ])
                            ->disableOptionWhen(fn (string $value): bool => $value === ExportOptions::FREEZE && ! $this->freezeAvailable())
                            // a choice that stopped being possible since (the worker's proof expired) is refused with the real reason at
                            // the start, not with "invalid choice" on a step the person is no longer looking at
                            ->in([ExportOptions::LIVE, ExportOptions::FREEZE])
                            ->default(ExportOptions::LIVE)
                            ->live(),
                        Toggle::make('hold')
                            ->label(__('migration.ui.export.hold'))
                            ->visible(fn (): bool => $this->freezeChosen()),
                        TextInput::make('hold_minutes')
                            ->label(__('migration.ui.export.hold_minutes'))
                            ->numeric()->minValue(0)
                            ->visible(fn (): bool => $this->freezeChosen()),
                        Text::make(fn (): string => $this->estimateText()),
                    ]),
                Step::make(__('migration.ui.export.step_protect'))
                    ->schema([
                        Toggle::make('include_secrets')
                            ->label(__('migration.ui.export.secrets'))
                            ->helperText(__('migration.ui.export.secrets_help'))
                            ->disabled(fn (): bool => ($this->exportData['preset'] ?? '') === Preset::Staging->value),
                        Text::make(fn (): string => app(PassphraseGuidance::class)->describe()),
                        TextInput::make('passphrase')
                            ->label(__('migration.ui.export.passphrase'))
                            ->password()->revealable()
                            ->required()
                            ->suffixAction(
                                Action::make('makePassphrase')
                                    ->label(__('migration.ui.export.make_passphrase'))
                                    ->icon('heroicon-o-sparkles')
                                    ->action(function ($set): void {
                                        $made = PassphraseInput::generate();
                                        $set('passphrase', $made);
                                        $set('passphrase_confirmation', $made);
                                        Notification::make()->title(__('migration.ui.export.passphrase_made_title'))->body(__('migration.ui.export.passphrase_made_body', ['passphrase' => $made]))->warning()->persistent()->send();
                                    })
                            )
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                $problem = app(PassphraseGuidance::class)->problemWith((string) $value);
                                $problem !== null && $fail($problem);
                            }),
                        TextInput::make('passphrase_confirmation')
                            ->label(__('migration.ui.export.passphrase_again'))
                            ->password()->revealable()
                            ->required()
                            ->same('passphrase'),
                        Callout::make(__('migration.ui.export.passphrase_warning_title'))
                            ->description(__('migration.ui.export.passphrase_warning_text'))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->warning(),
                    ])
                    ->afterValidation(fn () => $this->countExportFiles()),
                Step::make(__('migration.ui.export.step_review'))
                    ->schema([
                        Text::make(fn (): string => $this->reviewText()),
                        Text::make(fn (): string => $this->estimateText()),
                        $this->noticesSection('notices-review'),
                        $this->notScrubbedSection('not-scrubbed-review'),
                        TextInput::make('password')->label(__('migration.ui.password'))->password()->revealable()->required(),
                    ]),
            ])
                ->alpineSubmitHandler('$wire.startExport()')
                ->submitAction(WizardSubmit::button(__('migration.ui.export.start'), 'startExport', 'primary', 'heroicon-o-play'))
                ->skippable(false),
        ]);
    }

    // ---- what the wizard tells the person ---------------------------------------------------------------------------

    private function freezeChosen(): bool
    {
        return ($this->exportData['consistency'] ?? ExportOptions::LIVE) === ExportOptions::FREEZE;
    }

    /** A freeze is offered only when a queue worker has PROVEN it serves the migration queue (a configured queue alone proves nothing). */
    private function freezeAvailable(): bool
    {
        return config('queue.default') !== 'sync' && app(MigrationWorkerProbe::class)->isFresh();
    }

    private function freezeDescription(): string
    {
        $base = __('migration.ui.export.freeze_text');

        if ($this->freezeAvailable()) {
            return $base;
        }

        $command = $this->cliCommand(true);
        $why = config('queue.default') === 'sync'
            ? __('migration.ui.export.freeze_unavailable_sync', ['command' => $command])
            : __('migration.ui.export.freeze_unavailable_worker', ['worker' => $this->workerLine(), 'command' => $command]);

        return $base.' '.$why;
    }

    private function workerLine(): string
    {
        return 'php artisan queue:work --queue='.implode(',', [MigrationQueues::migration(), ...MigrationQueues::customer()]);
    }

    /** The command line that does what the wizard is about to do, for a server that cannot do it from this page. */
    private function cliCommand(bool $freeze = false): string
    {
        $selection = $this->selection();
        $parts = ['php artisan oeparts:migrate:export'];

        if ($selection instanceof Preset) {
            $parts[] = '--preset='.$selection->value;
        } elseif ($selection !== []) {
            $parts[] = '--groups='.implode(',', array_map(static fn (GroupKey $k): string => $k->value, $selection));
        } else {
            $parts[] = '--preset='.Preset::FullLive->value;
        }

        $freeze && $parts[] = '--freeze';
        ! empty($this->exportData['include_secrets']) && ($this->exportData['preset'] ?? '') !== Preset::Staging->value && $parts[] = '--include-secrets';
        $parts[] = '--generate-passphrase';

        return implode(' ', $parts);
    }

    /** What is NOT scrambled in a scrambled copy, in plain sentences (the same list the package report carries). */
    private function notScrubbedSection(string $key): Section
    {
        return Section::make(__('migration.ui.export.not_scrubbed_title'))
            ->description(__('migration.ui.export.not_scrubbed_text'))
            ->key($key)
            ->compact()
            ->collapsed()
            ->visible(fn (): bool => ($this->exportData['preset'] ?? '') === Preset::Staging->value)
            ->schema(fn (): array => array_map(
                static fn (array $row): Text => Text::make($row['why']),
                PiiMap::notScrubbedForReport(),
            ));
    }

    /**
     * What the plan says that is not a blocker but a person should know before starting: references that will point at nothing
     * (a setting that names a part that is left out), parts that were taken out of a scrambled copy, tables nobody has classified.
     *
     * @return list<string>
     */
    private function planNotices(): array
    {
        $plan = $this->exportPlan();
        $text = app(MigrationText::class);
        $lines = [];

        foreach ($plan->warnings as $warning) {
            $lines[] = $text->get('logical.'.$warning['id'], ['count' => $warning['count'], 'approximate' => $warning['approximate'] ? __('migration.ui.export.about').' ' : '']);
        }

        // Optional references (an order's customer, an order line's product) to a part that is left out: the link is emptied in the
        // copy, never a half-link to nothing. The rows are the table's own estimate, so this costs no extra count.
        foreach ($plan->nullified as $table => $columns) {
            $rows = (int) ($plan->estimate->tables[$table]['rows'] ?? 0);

            if ($rows > 0) {
                $owner = app(GroupRegistry::class)->ownerOf($table);
                $lines[] = __('migration.ui.export.cut_line', [
                    'part' => $owner === null ? $table : __('migration.group.'.$owner->value.'.label'),
                    'rows' => number_format($rows),
                    'links' => implode(', ', $columns),
                ]);
            }
        }

        foreach ($plan->dropped as $dropped) {
            $lines[] = __('migration.ui.export.dropped_line', ['group' => __('migration.group.'.$dropped['group'].'.label')]);
        }

        if ($plan->unclassified !== []) {
            $lines[] = __('migration.ui.export.unclassified_line', ['tables' => implode(', ', $plan->unclassified)]);
        }

        return $lines;
    }

    private function noticesSection(string $key): Section
    {
        return Section::make(__('migration.ui.export.notices_title'))
            ->key($key)
            ->compact()
            ->icon('heroicon-o-exclamation-triangle')
            ->visible(fn (): bool => $this->planNotices() !== [])
            ->schema(fn (): array => array_map(static fn (string $line): Text => Text::make($line), $this->planNotices()));
    }

    /** @return Preset|array<int, GroupKey> */
    private function selection(): Preset|array
    {
        $preset = (string) ($this->exportData['preset'] ?? 'full_live');

        if ($preset !== 'custom') {
            return Preset::tryFrom($preset) ?? Preset::FullLive;
        }

        return array_values(array_filter(array_map(static fn (string $g): ?GroupKey => GroupKey::tryFrom($g), array_map('strval', (array) ($this->exportData['groups'] ?? [])))));
    }

    /** @return list<string> the groups of the choice before dependencies are added, sorted */
    private function selectedGroupNames(): array
    {
        $selection = $this->selection();
        $groups = array_map(static fn (GroupKey $k): string => $k->value, $selection instanceof Preset ? $selection->groups() : $selection);
        sort($groups);

        return $groups;
    }

    private function exportOptions(): ExportOptions
    {
        $minutes = $this->exportData['hold_minutes'] ?? null;

        return new ExportOptions(
            consistency: $this->freezeChosen() ? ExportOptions::FREEZE : ExportOptions::LIVE,
            includeSecrets: (bool) ($this->exportData['include_secrets'] ?? false),
            hold: (bool) ($this->exportData['hold'] ?? true),
            holdReleaseMinutes: $minutes === null || $minutes === '' ? null : max(0, (int) $minutes),
        );
    }

    /**
     * @param  bool  $countFilesNow  walk the disk now (for the plan a run is started from); otherwise the count made at the review step is used
     */
    private function exportPlan(bool $countFilesNow = false): ExportPlan
    {
        $files = $countFilesNow ? $this->countFilesOfChoice() : $this->knownFileCount();
        $key = md5(json_encode([$this->exportData['preset'] ?? null, $this->selectedGroupNames(), $this->exportData['consistency'] ?? null, $this->exportData['include_secrets'] ?? null, $this->exportData['hold'] ?? null, $this->exportData['hold_minutes'] ?? null, $files]) ?: '');

        return $this->exportPlans[$key] ??= app(ExportPlanner::class)->plan($this->selection(), $this->exportOptions(), $files);
    }

    /** @return array{count: int, bytes: int, complete: bool}|null */
    private function knownFileCount(): ?array
    {
        $known = $this->exportFileCount;

        if (! is_array($known) || ($known['key'] ?? null) !== md5(implode(',', $this->selectedGroupNames()))) {
            return null;
        }

        return ['count' => (int) $known['count'], 'bytes' => (int) $known['bytes'], 'complete' => (bool) $known['complete']];
    }

    /** @return array{count: int, bytes: int, complete: bool} */
    private function countFilesOfChoice(): array
    {
        $selection = $this->selection();
        $groups = $selection instanceof Preset ? $selection->groups() : $selection;
        $roots = array_map(FileRoot::parse(...), array_map('strval', app(GroupRegistry::class)->fileRootsOf([GroupKey::Core, ...$groups])));

        if ($roots === []) {
            return ['count' => 0, 'bytes' => 0, 'complete' => true];
        }

        // a short, budgeted walk: the page must answer; a big file tree is reported as "at least". (A test binds its own counter.)
        $counter = app()->bound(FileCounter::class) ? app(FileCounter::class) : FileCounter::forApplication();
        $result = $counter->advance($roots, null, 1.5);

        return ['count' => $result['count'], 'bytes' => $result['bytes'], 'complete' => (bool) $result['done']];
    }

    /** Called when the person leaves the "protect it" step: count the files once so the review shows a real size. */
    private function countExportFiles(): void
    {
        $this->exportFileCount = ['key' => md5(implode(',', $this->selectedGroupNames()))] + $this->countFilesOfChoice();
    }

    private function announceAdded(): void
    {
        $text = $this->addedText();
        $text !== '' && Notification::make()->title(__('migration.ui.export.added_title'))->body($text)->info()->send();
    }

    private function addedText(): string
    {
        $lines = [];

        foreach ($this->exportPlan()->added as $group => $reasons) {
            $because = $reasons[0]['because_of'] ?? null;
            $lines[] = $because !== null
                ? __('migration.ui.export.added_line', ['group' => __('migration.group.'.$group.'.label'), 'because' => __('migration.group.'.$because.'.label')])
                : __('migration.ui.export.added_line_plain', ['group' => __('migration.group.'.$group.'.label')]);
        }

        return implode(' ', $lines);
    }

    private function estimateText(): string
    {
        $plan = $this->exportPlan();
        $e = $plan->estimate;
        $files = match (true) {
            $e->files['count'] <= 0 && ! ($e->files['complete'] ?? false) => __('migration.ui.export.estimate_files_unknown'),
            ! ($e->files['complete'] ?? false) => __('migration.ui.export.estimate_files_partial', ['size' => Number::fileSize($e->files['bytes'])]),
            default => __('migration.ui.export.estimate_files', ['size' => Number::fileSize($e->files['bytes'])]),
        };

        $text = __('migration.ui.export.estimate', ['data' => Number::fileSize($e->databaseBytes), 'files' => $files, 'low' => $e->minutesLow, 'high' => $e->minutesHigh]);

        return $plan->isFreeze() ? $text.__('migration.ui.export.estimate_freeze', ['minutes' => $plan->freezeCapMinutes]) : $text;
    }

    private function reviewText(): string
    {
        $plan = $this->exportPlan();

        return __('migration.ui.export.review', [
            'parts' => implode(', ', array_map(static fn (string $g): string => __('migration.group.'.$g.'.label'), $plan->groups)),
            'mode' => __('migration.ui.export.'.($plan->isFreeze() ? 'review_freeze' : 'review_live')),
            'secrets' => __('migration.ui.export.'.($plan->includeSecrets ? 'review_secrets_in' : 'review_secrets_out')),
            'scrambled' => $plan->anonymise ? ' '.__('migration.ui.export.review_scrambled') : '',
        ]);
    }

    // ---- starting and following the run -----------------------------------------------------------------------------

    public function startExport(): void
    {
        abort_unless(MigrationAccess::canExport(), 403);

        $data = $this->validatedExportState();
        $this->confirmPassword($data, 'exportData.password');

        $limit = max(1, (int) config('migration.export.starts_per_hour', 3));

        if (RateLimiter::tooManyAttempts(self::EXPORT_STARTS_KEY, $limit)) {
            Notification::make()
                ->title(__('migration.ui.export.limit_title'))
                ->body(__('migration.ui.export.limit_text', ['count' => $limit, 'minutes' => max(1, (int) ceil(RateLimiter::availableIn(self::EXPORT_STARTS_KEY) / 60))]))
                ->danger()->persistent()->send();

            return;
        }

        $plan = $this->exportPlan(true);

        if (! $plan->isRunnable()) {
            $first = $plan->blockers[0];
            $this->refuseExport(new MigrationException(MigrationErrorCode::from($first['code']), $first['params']));

            return;
        }

        try {
            $run = app(ExportManager::class)->startExport($plan, (string) $data['passphrase'], 'ui');
        } catch (MigrationException $e) {
            $this->refuseExport($e);

            return;
        }

        RateLimiter::hit(self::EXPORT_STARTS_KEY, 3600);

        $this->exportRunId = (int) $run->getKey();
        $this->exportFileCount = null;
        $this->getSchema('exportForm')->fill($this->exportDefaults()); // nothing secret stays in the page, and the wizard starts over
        $this->getSchema('exportForm')->dispatchClientSideStateReset();
        Notification::make()->title(__('migration.ui.export.started_title'))->body(__('migration.ui.export.'.($plan->isFreeze() ? 'started_freeze' : 'started_live')))->success()->send();
        $this->dispatch('migration-changed');
    }

    /**
     * The whole wizard's answers, validated. A field on an EARLIER step can be wrong (the person is looking at the last one), so
     * the first complaint is also said out loud; the field errors are kept for when they go back.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatedExportState(): array
    {
        try {
            return $this->getSchema('exportForm')->getState();
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            Notification::make()->title(__('migration.ui.export.check_steps_title'))->body((string) $first)->danger()->send();

            throw $e;
        }
    }

    private function refuseExport(MigrationException $e): void
    {
        $text = app(MigrationText::class)->forException($e);
        Notification::make()->title($text['title'])->body(trim($text['message'].' '.$text['fix']))->danger()->persistent()->send();
    }

    public function exportRun(): ?BackupRun
    {
        return $this->exportRunId ? BackupRun::query()->exports()->find($this->exportRunId) : null;
    }

    public function exportProgress(): ?MigrationProgress
    {
        $run = $this->exportRun();

        if ($run === null) {
            return null;
        }

        $progress = match ($run->status) {
            BackupRun::STATUS_SUCCESS => BackupProgress::success($run),
            BackupRun::STATUS_FAILED => BackupProgress::failed($run, (string) ($run->error_message ?? '')),
            default => BackupProgress::running($run, null, (string) $run->message, (int) $run->percent),
        };

        return app(ProgressPresenter::class)->present($progress, $run);
    }

    /** Called by the page's poll: moves a live-copy export that this page started (a freeze is never driven from here). */
    public function pollExport(): void
    {
        abort_unless(MigrationAccess::canExport(), 403);

        $run = $this->exportRun();

        if ($run === null || $run->isTerminal()) {
            return;
        }

        if ((($run->meta['export_plan']['consistency'] ?? ExportOptions::LIVE) === ExportOptions::FREEZE)) {
            return;
        }

        try {
            app(ExportManager::class)->advance($run);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function cancelExport(): void
    {
        abort_unless(MigrationAccess::canExport(), 403);

        $run = $this->exportRun();

        if ($run !== null && ! $run->isTerminal()) {
            app(ExportManager::class)->fail($run, 'Cancelled by '.(auth('admin')->user()?->name ?? 'an admin').'.');
            Notification::make()->title(__('migration.ui.export.cancelled_title'))->success()->send();
        }
    }

    public function dismissExport(): void
    {
        abort_unless(MigrationAccess::canExport(), 403);

        $this->exportRunId = null;
    }

    /** The same as `php artisan oeparts:migrate:unfreeze`: an export still copying is stopped, then the shop opens. */
    public function reopenShop(): void
    {
        abort_unless(MigrationAccess::canExport(), 403);

        $state = FreezeState::read();
        $run = $state === null ? null : BackupRun::query()->exports()->whereKey($state->runId)->first();

        if ($run !== null && ! $run->isTerminal()) {
            // stopping the run releases the freeze that belongs to it and says why in the run's history
            app(ExportManager::class)->fail($run, 'Unfrozen by an admin while the export was running: the copy is not consistent.');
        }

        app(ExportFreeze::class)->release('an admin, from the Migration page');

        if ($state !== null) {
            try {
                app(PackageAudit::class)->record('export.unfrozen', (string) $state->runId, ['by' => 'admin panel', 'admin' => auth('admin')->id()]);
            } catch (\Throwable $e) {
                report($e); // the shop is open either way; the history line is best effort
            }
        }

        Notification::make()->title(__('migration.ui.export.released_title'))->success()->send();
        $this->dispatch('migration-changed');
    }

    /** The run that is going, or the one that just finished, as a stock Section. Polls only while it is going. */
    protected function exportRunSection(): Section
    {
        $run = fn (): ?BackupRun => $this->exportRun();
        $progress = fn (): ?MigrationProgress => $this->exportProgress();
        $terminal = fn (): bool => (bool) $run()?->isTerminal();
        $freeze = fn (): bool => (($run()?->meta['export_plan']['consistency'] ?? 'live') === ExportOptions::FREEZE);

        return Section::make(fn (): string => __('migration.ui.export.run_title', [
            'id' => $run()?->getKey(),
            'state' => __('migration.ui.export.'.($terminal() ? ($run()?->status === BackupRun::STATUS_SUCCESS ? 'run_finished' : 'run_failed') : 'run_running')),
        ]))
            ->key('export-run-section')
            ->icon('heroicon-o-arrow-path')
            ->description(fn (): ?string => $terminal() ? null : ($progress()?->message ?? __('migration.ui.export.run_working')))
            ->visible(fn (): bool => $run() !== null)
            ->extraAttributes(fn (): array => $run() !== null && ! $terminal() ? ($freeze() ? ['wire:poll.5s' => ''] : ['wire:poll.2s' => 'pollExport']) : [])
            ->headerActions([
                Action::make('reopenShop')
                    ->label(__('migration.ui.export.release'))
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->size('sm')
                    ->visible(fn (): bool => $freeze() && FreezeState::read() !== null)
                    ->requiresConfirmation()
                    ->modalHeading(__('migration.ui.export.release_heading'))
                    ->modalDescription(__('migration.ui.export.release_text'))
                    ->action(fn () => $this->reopenShop()),
                Action::make('cancelExport')
                    ->label(__('migration.ui.export.cancel'))
                    ->icon('heroicon-o-stop')
                    ->color('gray')
                    ->size('sm')
                    ->visible(fn (): bool => ! $terminal())
                    ->requiresConfirmation()
                    ->modalHeading(__('migration.ui.export.cancel_heading'))
                    ->modalDescription(__('migration.ui.export.cancel_text'))
                    ->action(fn () => $this->cancelExport()),
                Action::make('dismissExport')
                    ->label(__('migration.ui.export.dismiss'))
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->size('sm')
                    ->visible($terminal)
                    ->action(fn () => $this->dismissExport()),
            ])
            ->schema([
                Text::make(fn (): string => ($progress()?->percent() ?? 0).'% — '.($progress()?->message ?? ''))->visible(fn (): bool => ! $terminal()),
                Callout::make(__('migration.ui.export.freeze_note_title'))
                    ->description(__('migration.ui.export.freeze_note_text'))
                    ->icon('heroicon-o-information-circle')
                    ->info()
                    ->visible(fn (): bool => $freeze() && ! $terminal()),
                Callout::make(__('migration.ui.export.ready_title'))
                    ->description(__('migration.ui.export.ready_text'))
                    ->icon('heroicon-o-check-circle')
                    ->success()
                    ->visible(fn (): bool => $run()?->status === BackupRun::STATUS_SUCCESS),
                Callout::make(__('migration.ui.export.failed_title'))
                    ->description(fn (): string => (string) ($run()?->error_message ?: __('migration.ui.export.failed_text')))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->danger()
                    ->visible(fn (): bool => $run()?->status === BackupRun::STATUS_FAILED),
            ]);
    }
}
