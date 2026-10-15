<?php

namespace App\Filament\Pages\System;

use App\Filament\Widgets\Instance\MaskedSettingsTable;
use App\Filament\Widgets\Instance\ReadinessChecksTable;
use App\Security\ReauthGate;
use App\Services\Instance\ActivityDiscarder;
use App\Services\Instance\InstanceEnv;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstancePromoter;
use App\Services\Instance\InstanceState;
use App\Services\Instance\PromotionBlockedException;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Instance\Readiness\ReadinessRegistry;
use App\Services\Instance\Readiness\ReadinessReport;
use App\Services\Instance\Readiness\ReadinessRunner;
use App\Services\Instance\SafeMode;
use App\Services\Instance\SystemGate;
use App\Services\Migration\Import\ImportSettings;
use App\Services\Migration\Ux\MigrationAccess;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * System → Site mode: what this installation is (the real shop, a testing copy, or a copy that is not trusted yet), the launch
 * checks, and the two ways out of a safe mode: releasing a site from quarantine and taking a testing copy live. Built only from
 * stock Filament parts (tabs, tables, sections, callouts, actions) so it follows the panel's look, dark mode and upgrades.
 *
 * The page decides nothing. The mode, the checks and the gates are the instance services' (InstancePromoter, ReadinessRunner,
 * InstanceModeSwitcher, ActivityDiscarder, SafeMode); this page asks them, shows the answer and passes the person's choices on,
 * exactly like the oeparts:instance:* commands do. A refusal is shown with the service's own sentence.
 *
 * Checking is request-chained: "Run checks" does one check at once and the page then polls (every 2 seconds, only while it is
 * checking) for as many more as fit in a few seconds, so a slow check on a big shop never has to finish inside one web request.
 * Releasing and going live ask for the admin's password again, and going live also for the site's own host, typed out.
 *
 * Gated by the `import migration` permission (super_admin only, like the rest of the migration family).
 */
class InstanceReadiness extends Page
{
    protected static ?string $slug = 'system/site-mode';

    /** More than a handful of testers does not belong in a .env line. */
    public const ALLOWLIST_MAX = 50;

    /** At most this long inside one poll, and never more than half of max_execution_time. */
    private const CHUNK_SECONDS = 8.0;

    /** The report, read from its file once per request (private: Livewire keeps nothing of it between requests). */
    private ?ReadinessReport $report = null;

    private bool $reportLoaded = false;

    /** True while a run started on this page is going on: the page polls and carries it on. */
    #[Locked]
    public bool $checking = false;

    /** @var array<string, mixed> the mail allow-list form */
    public array $mailData = [];

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 44;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationLabel(): string
    {
        return __('instance.page.title');
    }

    /** A copy shows what it is in the sidebar too, so nobody mistakes it for the real shop. */
    public static function getNavigationBadge(): ?string
    {
        $mode = app(SafeMode::class)->mode();

        return $mode->isProduction() ? null : strtoupper($mode->value);
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return app(SafeMode::class)->mode() === InstanceMode::Staging ? 'warning' : 'danger';
    }

    public static function canAccess(): bool
    {
        return MigrationAccess::canImport();
    }

    public function getTitle(): string
    {
        return __('instance.page.title');
    }

    public function getSubheading(): ?string
    {
        return __('instance.page.subheading');
    }

    public function mount(): void
    {
        $this->getSchema('form')->fill(['allowlist' => app(SafeMode::class)->mailAllowList()]);
    }

    /* ------------------------------------------------------------------ *
     * What the page reads
     * ------------------------------------------------------------------ */

    private function safe(): SafeMode
    {
        return app(SafeMode::class);
    }

    private function mode(): InstanceMode
    {
        return $this->safe()->mode();
    }

    /** The report as the runner keeps it (the command writes the same file); null before any check ran. Read once per request. */
    private function lastReport(): ?ReadinessReport
    {
        if (! $this->reportLoaded) {
            $report = app(ReadinessRunner::class)->last();
            $this->report = $report !== null && $report->checks !== [] ? $report : null;
            $this->reportLoaded = true;
        }

        return $this->report;
    }

    private function forgetReport(): void
    {
        $this->report = null;
        $this->reportLoaded = false;
    }

    private function appHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    private function by(): string
    {
        return 'admin #'.(int) auth('admin')->id();
    }

    /* ------------------------------------------------------------------ *
     * Layout
     * ------------------------------------------------------------------ */

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runChecks')
                ->label(__('instance.page.run_checks'))
                ->icon('heroicon-o-play')
                ->color('gray')
                ->tooltip(__('instance.page.run_checks_tip'))
                ->authorize(fn (): bool => self::canAccess())
                ->disabled(fn (): bool => $this->checking)
                ->action(fn () => $this->runChecks()),
            $this->releaseAction(),
            $this->goLiveAction(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(__('instance.page.invalid_title'))
                ->description(fn (): string => __('instance.page.invalid_text', ['value' => trim((string) config('instance.mode'))]))
                ->icon('heroicon-o-exclamation-triangle')
                ->danger()
                ->visible(fn (): bool => $this->safe()->hasInvalidValue()),

            Callout::make(__('instance.overview.drift_title'))
                ->description(fn (): string => (string) app(InstanceState::class)->drift($this->mode()))
                ->icon('heroicon-o-exclamation-triangle')
                ->warning()
                ->visible(fn (): bool => app(InstanceState::class)->drift($this->mode()) !== null),

            $this->runSection(),

            Tabs::make('SiteMode')
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs([
                    Tab::make(__('instance.tabs.overview'))
                        ->icon('heroicon-o-home')
                        ->schema($this->overviewSchema()),
                    Tab::make(__('instance.tabs.readiness'))
                        ->icon('heroicon-o-clipboard-document-check')
                        ->schema([
                            Text::make(__('instance.readiness.from_server'))->color('gray')->size(TextSize::Small),
                            Livewire::make(ReadinessChecksTable::class),
                        ]),
                    Tab::make(__('instance.tabs.safe_mode'))
                        ->icon('heroicon-o-lock-closed')
                        ->schema($this->safeModeSchema()),
                ]),
        ]);
    }

    /** The progress of a run started here. Polls only while it is going on. */
    private function runSection(): Section
    {
        return Section::make(__('instance.run.title'))
            ->key('run-section')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => $this->checking)
            ->extraAttributes(fn (): array => $this->checking ? ['wire:poll.2s' => 'advanceChecks'] : [])
            ->schema([
                Flex::make([
                    Text::make(fn (): string => $this->progress()['percent'].'%')->size(TextSize::Large)->weight(FontWeight::Bold)->grow(false),
                    Text::make(fn (): string => __('instance.run.progress', ['done' => $this->progress()['done'], 'total' => $this->progress()['total']])),
                ]),
                Text::make(fn (): string => $this->progress()['last'] === '' ? '' : __('instance.run.last', ['label' => $this->progress()['last']]))
                    ->color('gray')
                    ->size(TextSize::Small),
                Text::make(__('instance.run.keep_open'))->color('gray')->size(TextSize::Small),
            ]);
    }

    /** @return array{done: int, total: int, percent: int, last: string} */
    private function progress(): array
    {
        $report = $this->lastReport();
        $done = $report === null ? 0 : count($report->checks);
        $total = max($done, count(app(ReadinessRegistry::class)->probes([ReadinessProbe::STATIC, ReadinessProbe::LIVE])));
        $last = $report === null || $report->checks === [] ? null : $report->checks[array_key_last($report->checks)];

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? min(99, (int) floor($done * 100 / $total)) : 0,
            'last' => $last instanceof ReadinessCheck ? $last->label : '',
        ];
    }

    /* ------------------------------------------------------------------ *
     * Overview tab
     * ------------------------------------------------------------------ */

    /** @return list<Callout|Section> */
    private function overviewSchema(): array
    {
        $line = static fn (string $label, Closure $value): Text => Text::make(fn (): string => $label.': '.$value());

        return [
            Callout::make(fn (): string => __('instance.mode.'.$this->mode()->value))
                ->description(fn (): string => __('instance.mode.'.$this->mode()->value.'_text'))
                ->icon(fn (): string => $this->mode()->isProduction() ? 'heroicon-o-check-circle' : 'heroicon-o-shield-exclamation')
                ->status(fn (): string => match ($this->mode()) {
                    InstanceMode::Production => 'success',
                    InstanceMode::Staging => 'warning',
                    InstanceMode::Quarantine => 'danger',
                }),

            Section::make(__('instance.overview.this_site'))
                ->schema([
                    Flex::make([
                        Text::make(__('instance.overview.mode').':')->grow(false),
                        Text::make(fn (): string => __('instance.mode.'.$this->mode()->value))
                            ->badge()
                            ->color(fn (): string => match ($this->mode()) {
                                InstanceMode::Production => 'success',
                                InstanceMode::Staging => 'warning',
                                InstanceMode::Quarantine => 'danger',
                            })
                            ->grow(false),
                    ]),
                    $line(__('instance.overview.instance_id'), fn (): string => (string) (config('instance.id') ?: __('instance.overview.none'))),
                    $line(__('instance.overview.site_address'), fn (): string => (string) config('app.url')),
                    $line(__('instance.overview.held_back'), fn (): string => ($reasons = app(SystemGate::class)->reasons()) === [] ? __('instance.overview.held_nothing') : implode('; ', $reasons)),
                    $line(__('instance.overview.ledger'), fn (): string => __('instance.overview.ledger_value', ['mode' => app(InstanceState::class)->recordedMode() ?? __('instance.overview.none')])),
                ]),

            Callout::make(__('instance.overview.next_title'))
                ->description(fn (): string => __('instance.overview.next_'.$this->mode()->value))
                ->icon('heroicon-o-arrow-right-circle')
                ->info(),

            Section::make(__('instance.overview.last_checks'))
                ->schema([
                    Text::make(fn (): string => $this->lastChecksLine()),
                    Callout::make(fn (): string => $this->lastReport()?->canRelease() ? __('instance.overview.clear') : __('instance.overview.blocked'))
                        ->icon(fn (): string => $this->lastReport()?->canRelease() ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                        ->status(fn (): string => $this->lastReport()?->canRelease() ? 'success' : 'danger')
                        ->visible(fn (): bool => $this->lastReport()?->complete === true),
                ]),

            Section::make(__('instance.overview.changes_title'))
                ->collapsible()
                ->schema([
                    Text::make(__('instance.overview.changes_empty'))->color('gray')->visible(fn (): bool => $this->recentChanges() === []),
                    UnorderedList::make(fn (): array => array_map(static fn (string $line): Text => Text::make($line), $this->recentChanges()))
                        ->columns(1)
                        ->visible(fn (): bool => $this->recentChanges() !== []),
                ]),
        ];
    }

    private function lastChecksLine(): string
    {
        $report = $this->lastReport();

        if ($report === null) {
            return __('instance.overview.never_checked');
        }

        $counts = $report->counts();
        $parts = [__('instance.overview.counts', [
            'pass' => $counts[ReadinessCheck::PASS], 'warn' => $counts[ReadinessCheck::WARN], 'fail' => $counts[ReadinessCheck::FAIL],
            'pending' => $counts[ReadinessCheck::PENDING], 'skip' => $counts[ReadinessCheck::SKIP],
        ])];

        if ($report->generatedAt !== '') {
            $parts[] = __('instance.overview.checked_when', ['when' => Carbon::parse($report->generatedAt)->diffForHumans()]).'.';
        }

        if (! $report->complete) {
            $parts[] = __('instance.overview.unfinished');
        }

        return implode(' ', $parts);
    }

    /**
     * The last few .env changes the instance code made: when, by whom and which KEYS. The ledger keeps the old and new values too;
     * they are never shown here.
     *
     * @return list<string>
     */
    private function recentChanges(): array
    {
        $lines = [];

        foreach (array_reverse(array_slice((array) app(InstanceState::class)->read()['env_history'], -5)) as $change) {
            if (! is_array($change)) {
                continue;
            }

            $lines[] = __('instance.overview.change_line', [
                'when' => isset($change['at']) ? Carbon::parse((string) $change['at'])->diffForHumans() : '?',
                'who' => (string) ($change['by'] ?? '?'),
                'keys' => implode(', ', array_keys((array) ($change['changes'] ?? []))),
            ]);
        }

        return $lines;
    }

    /* ------------------------------------------------------------------ *
     * Safe mode tab
     * ------------------------------------------------------------------ */

    /** @return list<Callout|Section|Livewire|Text> */
    private function safeModeSchema(): array
    {
        $isCopy = fn (): bool => $this->safe()->isCopy();
        $line = static fn (string $label, Closure $value): Text => Text::make(fn (): string => $label.': '.$value());

        return [
            Callout::make(__('instance.safe.production_title'))
                ->description(__('instance.safe.production_text'))
                ->icon('heroicon-o-check-circle')
                ->success()
                ->visible(fn (): bool => ! $isCopy()),

            Section::make(__('instance.safe.holds_title'))
                ->description(__('instance.safe.holds_text'))
                ->visible($isCopy)
                ->schema([
                    $line(__('instance.safe.mail'), fn (): string => $this->mailLine()),
                    $line(__('instance.safe.schedule'), fn (): string => __('instance.safe.schedule_value', [
                        'always' => $this->runs(SystemGate::ALWAYS), 'internal' => $this->runs(SystemGate::INTERNAL), 'customer' => $this->runs(SystemGate::CUSTOMER),
                    ])),
                    $line(__('instance.safe.web_cron'), fn (): string => __('instance.safe.web_cron_'.($this->safe()->httpCronAllowed() ? 'on' : 'off'))),
                    $line(__('instance.safe.numbers'), fn (): string => __('instance.safe.numbers_value', ['prefix' => $this->safe()->sequencePrefix('')])),
                    $line(__('instance.safe.search'), fn (): string => $this->safe()->robotsDirective() === null ? __('instance.safe.search_open') : __('instance.safe.search_value')),
                    $line(__('instance.safe.masking'), fn (): string => (string) count($this->safe()->activeOverrides())),
                ]),

            Livewire::make(MaskedSettingsTable::class)->visible($isCopy),

            Section::make(__('instance.allowlist.title'))
                ->description(__('instance.allowlist.description'))
                ->visible($isCopy)
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('allowlist-form')
                        ->livewireSubmitHandler('saveAllowList')
                        ->footer([
                            Actions::make([
                                Action::make('saveList')
                                    ->label(__('instance.allowlist.save'))
                                    ->submit('allowlist-form'),
                            ]),
                        ]),
                ]),
        ];
    }

    private function runs(string $class): string
    {
        return __('instance.safe.'.($this->safe()->scheduledAllowed($class) ? 'yes' : 'no'));
    }

    private function mailLine(): string
    {
        $safe = $this->safe();

        return match (true) {
            $safe->mode() === InstanceMode::Production => __('instance.safe.mail_production'),
            $safe->mode() === InstanceMode::Quarantine => __('instance.safe.mail_quarantine'),
            $safe->mailAllowList() === [] => __('instance.safe.mail_staging_none'),
            default => __('instance.safe.mail_staging_list', ['count' => count($safe->mailAllowList())]),
        };
    }

    /* ------------------------------------------------------------------ *
     * The mail allow-list
     * ------------------------------------------------------------------ */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('mailData')
            ->components([
                TagsInput::make('allowlist')
                    ->label(__('instance.allowlist.field'))
                    ->helperText(__('instance.allowlist.field_help', ['max' => self::ALLOWLIST_MAX]))
                    ->placeholder('tester@example.com')
                    ->splitKeys(['Tab', ',', ' '])
                    ->rules(['max:'.self::ALLOWLIST_MAX])
                    ->validationMessages(['max' => __('instance.allowlist.too_many', ['max' => self::ALLOWLIST_MAX])])
                    // Filament evaluates a rule that is a closure, so the closure returns the rule.
                    ->nestedRecursiveRules([
                        fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                            if (! self::isAllowListEntry((string) $value)) {
                                $fail(__('instance.allowlist.bad_entry', ['entry' => (string) $value]));
                            }
                        },
                    ]),
            ]);
    }

    /** An exact address (tester@example.com) or a whole domain (@example.com), in the characters .env can hold. */
    public static function isAllowListEntry(string $entry): bool
    {
        $entry = trim($entry);
        $local = '[a-z0-9_+\-]+(?:\.[a-z0-9_+\-]+)*';
        $domain = '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+';

        return strlen($entry) <= 254 && preg_match('/^(?:'.$local.')?@'.$domain.'$/i', $entry) === 1;
    }

    public function saveAllowList(): void
    {
        abort_unless(self::canAccess(), 403);

        // The real shop delivers all mail; an allow-list there would do nothing and would only confuse.
        if (! $this->safe()->isCopy()) {
            Notification::make()->title(__('instance.allowlist.not_a_copy'))->warning()->send();

            return;
        }

        $state = $this->getSchema('form')->getState();
        $entries = array_values(array_unique(array_filter(array_map(
            static fn (mixed $entry): string => strtolower(trim((string) $entry)),
            (array) ($state['allowlist'] ?? []),
        ), static fn (string $entry): bool => $entry !== '')));

        try {
            $result = app(InstanceEnv::class)->apply(['OE_MAIL_ALLOWLIST' => implode(',', $entries)], $this->by());
        } catch (\Throwable $e) {
            Log::error('The mail allow-list could not be saved: '.$e->getMessage());
            Notification::make()->title(__('instance.allowlist.failed'))->body(__('instance.allowlist.failed_body'))->danger()->send();

            return;
        }

        if ($result['changed'] === []) {
            Notification::make()->title(__('instance.allowlist.unchanged'))->body(__('instance.allowlist.unchanged_text'))->send();

            return;
        }

        $this->getSchema('form')->fill(['allowlist' => $this->safe()->mailAllowList()]);
        $this->dispatch('instance-changed');

        Notification::make()
            ->title(__('instance.allowlist.saved'))
            ->body(__($result['restarted'] ? 'instance.allowlist.saved_restarted' : 'instance.allowlist.saved_plain'))
            ->success()
            ->send();
    }

    /* ------------------------------------------------------------------ *
     * Running the checks
     * ------------------------------------------------------------------ */

    /** Start a new run: forget the old one, do the first check now and let the poll do the rest. */
    public function runChecks(): void
    {
        abort_unless(self::canAccess(), 403);

        if ($this->checking) {
            return;
        }

        try {
            app(ReadinessRunner::class)->reset();
        } catch (\Throwable $e) {
            Log::error('The launch checks could not be started: '.$e->getMessage());
            $this->checkFailed();

            return;
        }

        $this->forgetReport();
        $this->checking = true;

        Notification::make()->title(__('instance.run.started'))->success()->send();

        $this->advance(0.0);
    }

    /** One chunk of the run: as many checks as fit in a few seconds (at least one). Called by the poll while a run is going on. */
    public function advanceChecks(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->advance($this->chunkSeconds());
    }

    /** @param  float  $budget  seconds; 0 means exactly one check (the runner always does at least one) */
    private function advance(float $budget): void
    {
        if (! $this->checking) {
            return;
        }

        // A second tab (or a slow poll) must not run the same check twice: whoever holds the lock does this chunk.
        $lock = $this->advanceLock();

        if ($lock !== null && ! $lock->get()) {
            return;
        }

        try {
            $report = app(ReadinessRunner::class)->advance(ReadinessContext::make(null, request()->getHost()), $budget);
        } catch (\Throwable $e) {
            $this->checking = false;
            Log::error('The launch checks stopped: '.$e->getMessage());
            $this->checkFailed();

            return;
        } finally {
            $lock?->release();
        }

        $this->forgetReport();

        if ($report->complete) {
            $this->checking = false;

            Notification::make()
                ->title(__('instance.run.finished_title'))
                ->body($report->canRelease() ? __('instance.run.finished_clear') : __('instance.run.finished_blocked'))
                ->status($report->canRelease() ? 'success' : 'warning')
                ->send();
        }

        $this->dispatch('instance-changed');
    }

    /** Null when the cache cannot lock (a `null` store): the run then simply goes on without the guard. */
    private function advanceLock(): ?Lock
    {
        try {
            return Cache::lock('instance-readiness-advance', 120);
        } catch (\Throwable) {
            return null;
        }
    }

    private function chunkSeconds(): float
    {
        $max = (int) ini_get('max_execution_time');

        return $max > 0 ? min(self::CHUNK_SECONDS, $max / 2) : self::CHUNK_SECONDS;
    }

    private function checkFailed(): void
    {
        Notification::make()->title(__('instance.run.failed_title'))->body(__('instance.run.failed_body'))->danger()->send();
        $this->dispatch('instance-changed');
    }

    /* ------------------------------------------------------------------ *
     * Release and Go live
     * ------------------------------------------------------------------ */

    public function releaseAction(): Action
    {
        return Action::make('release')
            ->label(__('instance.release.action'))
            ->icon('heroicon-o-lock-open')
            ->color('primary')
            ->visible(fn (): bool => $this->mode() === InstanceMode::Quarantine)
            ->authorize(fn (): bool => self::canAccess())
            ->modalHeading(__('instance.release.heading'))
            ->modalDescription(__('instance.release.description'))
            ->modalSubmitActionLabel(__('instance.release.submit'))
            ->schema(fn (): array => $this->releaseSchema())
            ->action(function (array $data): void {
                abort_unless(self::canAccess(), 403);
                $this->confirmPassword($data);

                $to = InstanceMode::tryFrom((string) ($data['to'] ?? ''));

                try {
                    // The promoter runs every check again and decides; it throws a PromotionBlockedException when something blocks.
                    $result = app(InstancePromoter::class)->release($to ?? InstanceMode::Quarantine, $this->waivers($data), $this->by(), request()->getHost());
                } catch (PromotionBlockedException $e) {
                    $this->refused($e);

                    return;
                } catch (\Throwable $e) {
                    $this->unexpected($e, 'release');

                    return;
                }

                Notification::make()->title(__('instance.release.done_title'))->body(__('instance.release.done_body', ['mode' => __('instance.mode.'.$result['to'])]))->success()->send();
                $this->redirect(static::getUrl());
            });
    }

    public function goLiveAction(): Action
    {
        return Action::make('goLive')
            ->label(__('instance.go_live.action'))
            ->icon('heroicon-o-rocket-launch')
            ->color('danger')
            ->visible(fn (): bool => $this->mode() === InstanceMode::Staging)
            ->authorize(fn (): bool => self::canAccess())
            ->modalHeading(__('instance.go_live.heading'))
            ->modalDescription(__('instance.go_live.description'))
            ->modalSubmitActionLabel(__('instance.go_live.submit'))
            ->schema(fn (): array => $this->goLiveSchema())
            ->action(function (array $data): void {
                abort_unless(self::canAccess(), 403);
                $this->confirmPassword($data);

                try {
                    app(InstancePromoter::class)->goLive(
                        (string) ($data['host'] ?? ''),
                        ['discard_activity' => (bool) ($data['discard_activity'] ?? false), 'confirm_payments' => (bool) ($data['confirm_payments'] ?? false), 'waivers' => $this->waivers($data)],
                        $this->by(),
                        request()->getHost(),
                    );
                } catch (PromotionBlockedException $e) {
                    // These two belong to a field of the form: show them there, so the person can fix the field and press the button again.
                    if ($e->reason === 'host_not_typed') {
                        throw ValidationException::withMessages(['mountedActions.0.data.host' => [$e->getMessage()]]);
                    }

                    if ($e->reason === 'payments_unconfirmed' && $this->gatewayIsLive()) {
                        throw ValidationException::withMessages(['mountedActions.0.data.confirm_payments' => [$e->getMessage()]]);
                    }

                    $this->refused($e);

                    return;
                } catch (\Throwable $e) {
                    $this->unexpected($e, 'go-live');

                    return;
                }

                Notification::make()->title(__('instance.go_live.done_title'))->body(__('instance.go_live.done_body'))->success()->send();
                $this->redirect(static::getUrl());
            });
    }

    /** @return list<Component|Field> */
    private function releaseSchema(): array
    {
        $intent = InstanceMode::tryFrom((string) (app(InstanceState::class)->read()['import']['intent'] ?? ''));

        return [
            Callout::make(__('instance.release.no_report'))
                ->icon('heroicon-o-information-circle')
                ->info()
                ->visible(fn (): bool => $this->lastReport()?->complete !== true),

            Radio::make('to')
                ->label(__('instance.release.to'))
                ->options([
                    InstanceMode::Production->value => __('instance.release.to_production'),
                    InstanceMode::Staging->value => __('instance.release.to_staging'),
                ])
                ->helperText(__('instance.release.to_help'))
                ->default($intent !== null && $intent !== InstanceMode::Quarantine ? $intent->value : null)
                ->required(),

            ...$this->waiverFields(),
            $this->passwordField(),
        ];
    }

    /** @return list<Component|Field> */
    private function goLiveSchema(): array
    {
        $host = $this->appHost();
        $activity = app(ActivityDiscarder::class)->activity();
        $fields = [];

        if ($activity !== []) {
            $fields[] = Callout::make(__('instance.go_live.activity_title'))
                ->description(__('instance.go_live.activity_text', ['list' => implode(', ', array_map(static fn (string $table, int $rows): string => "{$rows} in {$table}", array_keys($activity), $activity))]))
                ->icon('heroicon-o-exclamation-triangle')
                ->warning();
            $fields[] = Toggle::make('discard_activity')
                ->label(__('instance.go_live.discard'))
                ->helperText(__('instance.go_live.discard_help'));
        }

        if ($this->gatewayIsLive()) {
            $fields[] = Toggle::make('confirm_payments')
                ->label(__('instance.go_live.payments'));
        }

        return [
            Callout::make(__('instance.go_live.expected', ['host' => $host]))
                ->icon('heroicon-o-globe-alt')
                ->info(),
            TextInput::make('host')
                ->label(__('instance.go_live.host'))
                ->helperText(__('instance.go_live.host_help', ['host' => $host]))
                ->required(),
            ...$fields,
            ...$this->waiverFields(),
            $this->passwordField(),
        ];
    }

    /** The card gateway's stored environment is "live". A read, not a decision: the promoter still decides and refuses. */
    private function gatewayIsLive(): bool
    {
        return strtolower((string) app(ImportSettings::class)->stored('payment', 'airwallex_environment')) === 'live';
    }

    /**
     * One field per failing check that may be waived, from the last report: the person types why it is fine to launch anyway
     * (the promoter wants at least ten characters, refuses any other check, and keeps the reason in the ledger).
     *
     * @return list<TextInput>
     */
    private function waiverFields(): array
    {
        $fields = [];

        foreach ($this->lastReport()->checks ?? [] as $check) {
            if ($check->isFail() && $check->overridable) {
                $fields[] = TextInput::make('waive_'.$check->key)
                    ->label(__('instance.confirm.waive_label', ['label' => $check->label]))
                    ->helperText(trim($check->message.' '.__('instance.confirm.waive_help')))
                    ->minLength(10)
                    ->maxLength(300)
                    ->validationMessages(['min' => __('instance.confirm.waive_short')]);
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data  the modal's form state
     * @return array<string, string> check key => the reason typed (only the filled ones)
     */
    private function waivers(array $data): array
    {
        $waivers = [];

        foreach ($data as $field => $reason) {
            if (str_starts_with((string) $field, 'waive_') && is_string($reason) && trim($reason) !== '') {
                $waivers[substr((string) $field, 6)] = trim($reason);
            }
        }

        return $waivers;
    }

    private function passwordField(): TextInput
    {
        return TextInput::make('password')
            ->label(__('instance.confirm.password'))
            ->helperText(__('instance.confirm.password_help'))
            ->password()
            ->revealable()
            ->required();
    }

    /**
     * "Type your password again". ReauthGate is the shared check, with its wrong-guess limit; it reports on a field called
     * `password`, but inside a modal the field lives at `mountedActions.0.data.password`, so the error is moved there or the person
     * would see nothing happen.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function confirmPassword(array $data): void
    {
        try {
            ReauthGate::confirm($data['password'] ?? null, 'password', __('instance.confirm.password_incorrect'));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['mountedActions.0.data.password' => $e->errors()['password'] ?? [__('instance.confirm.password_incorrect')]]);
        }
    }

    /** The promoter refused, with a sentence meant to be read. The checks it just ran are the freshest report: refresh the tables. */
    private function refused(PromotionBlockedException $e): void
    {
        $key = 'instance.blocked.'.$e->reason;

        Notification::make()
            ->title(Lang::has($key) ? __($key) : __('instance.blocked.default'))
            ->body($e->getMessage())
            ->danger()
            ->persistent()
            ->send();

        $this->forgetReport();
        $this->dispatch('instance-changed');
    }

    /** Anything else went wrong. The reason goes to the log, never to the screen. */
    private function unexpected(\Throwable $e, string $what): void
    {
        Log::error("Site mode: {$what} failed: ".$e->getMessage(), ['exception' => $e::class]);

        Notification::make()
            ->title(__('instance.blocked.unexpected_title'))
            ->body(__('instance.blocked.unexpected_body'))
            ->danger()
            ->persistent()
            ->send();

        $this->forgetReport();
        $this->dispatch('instance-changed');
    }
}
