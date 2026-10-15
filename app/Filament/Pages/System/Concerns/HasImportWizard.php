<?php

namespace App\Filament\Pages\System\Concerns;

use App\Filament\Widgets\Migration\Concerns\ConfirmsPassword;
use App\Filament\Widgets\Migration\GroupPickerSchema;
use App\Filament\Widgets\Migration\WizardSubmit;
use App\Security\PackageAudit;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Groups\Preset;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportTarget;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\TransferLimits;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferStatus;
use App\Services\Migration\Ux\AdminImportFlow;
use App\Services\Migration\Ux\MigrationAccess;
use App\Services\Migration\Ux\MigrationText;
use App\Services\Migration\Ux\UploadedPackageIntake;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The Import tab of System → Migration: a six-step wizard (where is the file, open it, what to bring, live or test, check,
 * confirm) that prepares a REAL import run, then hands over to the runner page.
 *
 * The run is created at "Open it" (the passphrase opens and verifies the package, and the checks start); the choices of the next
 * steps are written into the run; "Check" runs the checks again with them; "Confirm" asks for the typed REPLACE and the admin's
 * password and then sends the browser to /migrate/run/{id}#t=<token>. From that moment the admin panel is not used: the import
 * replaces the admin accounts, so no Livewire page could survive it. The runner token lives only in this component's locked state
 * and in the redirect (inside the address' fragment, which a browser never sends to a server); the server keeps a hash.
 *
 * Everything the person chose that the engine will rely on (the run, the token, the groups) is in LOCKED properties, which the
 * browser cannot change; the form state is only ever input.
 */
trait HasImportWizard
{
    use ConfirmsPassword;

    /** @var array<string, mixed> */
    public array $importData = [];

    #[Locked]
    public ?string $importRunId = null;

    #[Locked]
    public ?string $importToken = null;

    #[Locked]
    public ?string $transferId = null;

    /** @var list<string> the groups the person's choice resolves to (with what they cannot do without) */
    #[Locked]
    public array $importGroups = [];

    /** 'open' or 'check' while the run is still working on that, otherwise null */
    #[Locked]
    public ?string $importWorking = null;

    /** @var array<string, int> what this site holds now, rows per part: what the import empties */
    #[Locked]
    public array $importEmptied = [];

    /** @var array<string, mixed>|null the checks of the current run, read once per request */
    private ?array $importPreflightMemo = null;

    /** @var array{0: array<string, mixed>|null}|null another live run, read once per request (wrapped, because "none" is an answer too) */
    private ?array $importOtherRunMemo = null;

    public function mountHasImportWizard(): void
    {
        $this->getSchema('importForm')->fill($this->importDefaults());
    }

    /** @return array<string, mixed> */
    private function importDefaults(): array
    {
        return [
            'source' => 'inbox',
            'package' => null,
            'url' => null,
            'sha256' => null,
            'upload' => [],
            'passphrase' => null,
            'preset' => 'all',
            'groups' => [],
            'launch' => null,
            'site_url' => rtrim(request()->getSchemeAndHttpHost(), '/'),
            'include_secrets' => false,
            'mail_allowlist' => null,
            'acks' => [],
            'typed' => null,
            'password' => null,
        ];
    }

    /** @return array<int, Component> everything the Import tab shows */
    protected function importTabSchema(): array
    {
        return [
            Callout::make(__('migration.ui.import.trust_title'))
                ->description(__('migration.ui.import.trust_text'))
                ->icon('heroicon-o-shield-exclamation')
                ->warning(),
            $this->otherRunSection(),
            $this->importWorkingSection(),
            EmbeddedSchema::make('importForm'),
            Actions::make([
                Action::make('startOver')
                    ->label(__('migration.ui.import.cancel'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->tooltip(__('migration.ui.import.cancel_text'))
                    ->requiresConfirmation()
                    ->modalHeading(__('migration.ui.import.cancel'))
                    ->modalDescription(__('migration.ui.import.cancel_text'))
                    ->action(fn () => $this->cancelImport()),
            ])->key('import-start-over')->visible(fn (): bool => $this->importRunId !== null || $this->transferId !== null),
        ];
    }

    public function importForm(Schema $schema): Schema
    {
        return $schema->statePath('importData')->components([
            Wizard::make([
                Step::make(__('migration.ui.import.step_where'))
                    ->schema([
                        Radio::make('source')
                            ->label(__('migration.ui.import.source'))
                            ->options(['inbox' => __('migration.ui.import.source_inbox'), 'link' => __('migration.ui.import.source_link'), 'computer' => __('migration.ui.import.source_computer')])
                            ->descriptions([
                                'inbox' => __('migration.ui.import.source_inbox_text'),
                                'link' => __('migration.ui.import.source_link_text'),
                                'computer' => __('migration.ui.import.source_computer_text', ['mb' => UploadedPackageIntake::maxMegabytes()]),
                            ])
                            ->required()
                            ->live()
                            // a package chosen for the other way is not this way's: it comes from the folder list, the finished download or the upload
                            ->afterStateUpdated(function ($set): void {
                                $set('package', null);
                                $set('upload', []);
                            }),
                        // The stock Filament file field, for SMALL packages only. It adds no route, no limiter and no script of its own: Livewire's
                        // own upload endpoint serves it. The file stays a temporary upload until "Next" moves it into the import folder.
                        FileUpload::make('upload')
                            ->label(__('migration.ui.import.upload'))
                            ->helperText(__('migration.ui.import.upload_help', ['mb' => UploadedPackageIntake::maxMegabytes()]))
                            ->storeFiles(false)
                            ->maxSize((int) ceil(UploadedPackageIntake::maxBytes() / 1024))
                            ->validationMessages(['max' => $this->uploadTooLargeText()])
                            ->visible(fn (): bool => ($this->importData['source'] ?? '') === 'computer'),
                        Select::make('package')
                            ->label(__('migration.ui.import.package'))
                            ->options(fn (): array => $this->inboxOptions())
                            ->disableOptionWhen(fn (string $value): bool => ! $this->inboxStable($value))
                            ->helperText(fn (): ?string => $this->inboxOptions() === [] ? __('migration.ui.import.package_none') : null)
                            ->visible(fn (): bool => ($this->importData['source'] ?? 'inbox') === 'inbox')
                            ->required(fn (): bool => ($this->importData['source'] ?? 'inbox') === 'inbox'),
                        TextInput::make('url')
                            ->label(__('migration.ui.import.link'))
                            ->visible(fn (): bool => ($this->importData['source'] ?? '') === 'link'),
                        TextInput::make('sha256')
                            ->label(__('migration.ui.import.link_checksum'))
                            ->visible(fn (): bool => ($this->importData['source'] ?? '') === 'link'),
                        Actions::make([
                            Action::make('startFetch')
                                ->label(__('migration.ui.import.link_start'))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->action(fn () => $this->startImportFetch()),
                        ])->key('import-fetch-actions')->visible(fn (): bool => ($this->importData['source'] ?? '') === 'link' && $this->transferId === null),
                        Text::make(fn (): string => $this->fetchLine())
                            ->visible(fn (): bool => $this->transferId !== null)
                            ->extraAttributes(fn (): array => $this->fetchRunning() ? ['wire:poll.2s' => 'pollImportFetch'] : []),
                    ])
                    ->afterValidation(fn () => $this->requirePackage()),
                Step::make(__('migration.ui.import.step_open'))
                    ->schema([
                        TextInput::make('passphrase')
                            ->label(__('migration.ui.import.passphrase'))
                            ->helperText(__('migration.ui.import.passphrase_help'))
                            ->password()->revealable()
                            ->required(fn (): bool => $this->importRunId === null),
                    ])
                    ->afterValidation(fn () => $this->openImportPackage()),
                Step::make(__('migration.ui.import.step_bring'))
                    ->schema([
                        Text::make(fn (): string => $this->summaryText()),
                        Radio::make('preset')
                            ->label(__('migration.ui.import.preset'))
                            ->options(fn (): array => $this->importPresetOptions())
                            ->in(fn (): array => array_keys($this->importPresetOptions()))
                            ->live(),
                        GroupPickerSchema::make('groups', fn (): array => $this->heldGroups())
                            ->label(__('migration.ui.import.groups'))
                            ->helperText(__('migration.ui.import.groups_help'))
                            ->visible(fn (): bool => ($this->importData['preset'] ?? 'all') === 'custom'),
                    ])
                    ->afterValidation(fn () => $this->chooseImportGroups()),
                Step::make(__('migration.ui.import.step_launch'))
                    ->schema([
                        Radio::make('launch')
                            ->label(__('migration.ui.import.launch'))
                            ->options(['live' => __('migration.ui.import.launch_live'), 'staging' => __('migration.ui.import.launch_test')])
                            ->descriptions(['live' => __('migration.ui.import.launch_live_text'), 'staging' => __('migration.ui.import.launch_test_text')])
                            ->required()
                            ->live(),
                        TextInput::make('site_url')
                            ->label(__('migration.ui.import.site_url'))
                            ->helperText(__('migration.ui.import.site_url_help'))
                            ->required(),
                        Toggle::make('include_secrets')
                            ->label(__('migration.ui.import.secrets'))
                            ->helperText(__('migration.ui.import.secrets_help'))
                            ->visible(fn (): bool => $this->packageHasSecrets()),
                        TextInput::make('mail_allowlist')
                            ->label(__('migration.ui.import.mail_allowlist'))
                            ->helperText(__('migration.ui.import.mail_allowlist_help'))
                            ->visible(fn (): bool => ($this->importData['launch'] ?? '') === 'staging'),
                    ])
                    ->afterValidation(fn () => $this->runImportChecks()),
                Step::make(__('migration.ui.import.step_check'))
                    ->schema([
                        Section::make(__('migration.ui.import.checks_heading'))
                            ->key('import-checks')
                            ->schema(fn (): array => $this->checkLines()),
                        Callout::make(__('migration.ui.import.blocked_title'))
                            ->description(__('migration.ui.import.blocked_text'))
                            ->icon('heroicon-o-x-circle')
                            ->danger()
                            ->visible(fn (): bool => $this->importRunId !== null && ! $this->importCanProceed()),
                    ])
                    ->afterValidation(fn () => $this->requireImportReady()),
                Step::make(__('migration.ui.import.step_confirm'))
                    ->schema([
                        Callout::make(__('migration.ui.import.confirm_title'))
                            ->description(__('migration.ui.import.confirm_text'))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->danger(),
                        Callout::make(__('migration.ui.import.populated_title'))
                            ->description(fn (): string => __('migration.ui.import.populated_text', ['counts' => $this->populatedCounts() ?: __('migration.ui.import.populated_many')]))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->danger()
                            ->visible(fn (): bool => $this->importPopulated()),
                        Section::make(__('migration.ui.import.emptied_heading'))
                            ->description(__('migration.ui.import.emptied_text'))
                            ->key('import-emptied')
                            ->compact()
                            ->collapsed()
                            ->visible(fn (): bool => $this->importEmptied !== [])
                            ->schema(fn (): array => $this->emptiedLines()),
                        CheckboxList::make('acks')
                            ->label(__('migration.ui.import.acks'))
                            ->options(fn (): array => $this->ackOptions())
                            ->in(fn (): array => array_keys($this->ackOptions()))
                            ->required(fn (): bool => $this->ackOptions() !== [])
                            ->validationMessages(['required' => __('migration.ui.import.acks_all')])
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                array_diff(array_keys($this->ackOptions()), array_map('strval', (array) $value)) !== [] && $fail(__('migration.ui.import.acks_all'));
                            })
                            ->visible(fn (): bool => $this->ackOptions() !== []),
                        TextInput::make('typed')
                            ->label(__('migration.ui.import.typed'))
                            ->required()
                            ->in(['REPLACE'])
                            ->validationMessages(['in' => __('migration.ui.import.typed_wrong')]),
                        TextInput::make('password')->label(__('migration.ui.password'))->password()->revealable()->required(),
                    ]),
            ])
                ->alpineSubmitHandler('$wire.confirmImport()')
                ->submitAction(WizardSubmit::button(__('migration.ui.import.start'), 'confirmImport', 'danger', 'heroicon-o-arrow-path'))
                ->skippable(false),
        ]);
    }

    // ---- what the tab shows above the wizard -----------------------------------------------------------------------------

    /** Another import is alive (started from the command line, the installer, or a tab that was closed): say so. */
    private function otherRunSection(): Section
    {
        return Section::make(__('migration.ui.import.other_run_title'))
            ->key('import-other-run')
            ->icon('heroicon-o-exclamation-triangle')
            ->visible(fn (): bool => $this->otherRun() !== null)
            ->schema([
                Text::make(function (): string {
                    $other = $this->otherRun();

                    return $other === null ? '' : __('migration.ui.import.'.($other['discardable'] ? 'other_run_orphan' : 'other_run_text'), ['id' => $other['id'], 'status' => $other['status'], 'door' => $other['door']]);
                }),
                Actions::make([
                    Action::make('discardOtherRun')
                        ->label(__('migration.ui.import.other_run_discard'))
                        ->icon('heroicon-o-trash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading(__('migration.ui.import.other_run_discard'))
                        ->modalDescription(__('migration.ui.import.cancel_text'))
                        ->action(fn () => $this->discardOtherRun()),
                ])->key('import-other-run-actions')->visible(fn (): bool => ($this->otherRun()['discardable'] ?? false) === true),
            ]);
    }

    /** @return array{id: string, status: string, door: string, started: string, discardable: bool}|null */
    private function otherRun(): ?array
    {
        return ($this->importOtherRunMemo ??= [app(AdminImportFlow::class)->otherRun($this->importRunId)])[0];
    }

    public function discardOtherRun(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        $this->importOtherRunMemo = null;
        $other = $this->otherRun();

        if ($other !== null && $other['discardable']) {
            app(AdminImportFlow::class)->discard($other['id']);
            $this->importOtherRunMemo = null;
            Notification::make()->title(__('migration.ui.import.cancelled_title'))->success()->send();
        }
    }

    /** While a long check goes on in the background of the page, say how far it has come (and poll it). */
    private function importWorkingSection(): Section
    {
        return Section::make(__('migration.ui.import.working_title'))
            ->key('import-working')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => $this->importWorking !== null && $this->importRunId !== null)
            ->extraAttributes(fn (): array => $this->importWorking !== null ? ['wire:poll.3s' => 'pollImport'] : [])
            ->schema([
                Text::make(function (): string {
                    if ($this->importRunId === null) {
                        return '';
                    }

                    $progress = app(AdminImportFlow::class)->progress($this->importRunId);

                    return __('migration.ui.import.working_text', ['percent' => (int) floor($progress['percent']), 'detail' => $progress['detail']]);
                }),
            ]);
    }

    /** Called by the page's poll while a big package is still being checked: when it is ready, say so. */
    public function pollImport(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        if ($this->importWorking === null || $this->importRunId === null) {
            return;
        }

        $result = app(AdminImportFlow::class)->settle($this->importRunId, 8.0);
        $this->importPreflightMemo = null;

        if ($result['failed']) {
            $this->abandonRun($result);

            return;
        }

        if ($result['ready']) {
            $this->importWorking = null;
            Notification::make()->title(__('migration.ui.import.ready_title'))->body(__('migration.ui.import.ready_body'))->success()->send();
        }
    }

    // ---- step 1: where is the file --------------------------------------------------------------------------------------

    /** @return array<string, string> */
    private function inboxOptions(): array
    {
        $options = [];

        foreach (app(AdminImportFlow::class)->inboxPackages() as $p) {
            $options[$p['name']] = $p['stable']
                ? __('migration.ui.import.package_option', ['name' => $p['name'], 'size' => Number::fileSize($p['size']), 'when' => Carbon::createFromTimestamp($p['modified'])->diffForHumans()])
                : __('migration.ui.import.package_uploading', ['name' => $p['name']]);
        }

        return $options;
    }

    private function inboxStable(string $name): bool
    {
        foreach (app(AdminImportFlow::class)->inboxPackages() as $p) {
            if ($p['name'] === $name) {
                return $p['stable'];
            }
        }

        return false;
    }

    private function uploadTooLargeText(): string
    {
        $text = app(MigrationText::class)->error(MigrationErrorCode::UploadTooLarge);

        return trim($text['message'].' '.$text['fix']);
    }

    /**
     * "From this computer": the file that finished loading is moved into the import folder through the same checks as every other
     * way in (UploadedPackageIntake), and from then on it is a package of the folder like any other. Nothing is kept of the upload.
     */
    private function takeUploadedPackage(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        $file = collect(Arr::wrap($this->importData['upload'] ?? []))->first(fn (mixed $f): bool => $f instanceof TemporaryUploadedFile);

        if (! $file instanceof TemporaryUploadedFile) {
            if (empty($this->importData['package'])) {
                throw ValidationException::withMessages(['importData.upload' => __('migration.ui.import.upload_none')]);
            }

            return; // the file of an earlier press of "Next" is already in the folder
        }

        try {
            $this->importData['package'] = app(UploadedPackageIntake::class)->take((string) $file->getRealPath(), $file->getClientOriginalName(), (int) auth('admin')->id());
        } catch (MigrationException $e) {
            $this->failField('importData.upload', $e);
        } finally {
            $file->delete();
            $this->importData['upload'] = [];
        }

        Notification::make()->title(__('migration.ui.import.upload_saved_title'))->body((string) $this->importData['package'])->success()->send();
    }

    private function requirePackage(): void
    {
        ($this->importData['source'] ?? 'inbox') === 'computer' && $this->takeUploadedPackage();

        $name = (string) ($this->importData['package'] ?? '');

        if (($this->importData['source'] ?? 'inbox') === 'link' && ($name === '' || $this->transferId === null)) {
            throw ValidationException::withMessages(['importData.url' => __('migration.ui.import.link_not_ready')]);
        }

        if ($name === '') {
            throw ValidationException::withMessages(['importData.package' => __('migration.ui.import.package_none')]);
        }

        // Another package than the one the run was made for: that run belongs to the old choice.
        if ($this->importRunId !== null && app(AdminImportFlow::class)->packageName($this->importRunId) !== $name) {
            $this->dropRun();
        }
    }

    public function startImportFetch(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        try {
            $state = app(TransferManager::class)->start((string) ($this->importData['url'] ?? ''), ($this->importData['sha256'] ?? '') !== '' ? (string) $this->importData['sha256'] : null, null, 'admin');
        } catch (MigrationException $e) {
            $text = app(MigrationText::class)->forException($e);
            throw ValidationException::withMessages(['importData.url' => trim($text['message'].' '.$text['fix'])]);
        }

        $this->transferId = $state->id;
        $this->importData['url'] = null; // a link can carry a secret: it is encrypted in the transfer, not kept in the page
        Notification::make()->title(__('migration.ui.import.link_started_title'))->success()->send();
        $this->pollImportFetch();
    }

    public function pollImportFetch(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        if ($this->transferId === null) {
            return;
        }

        $state = app(TransferManager::class)->advance($this->transferId, app(TransferLimits::class)->requestBudgetSeconds());

        if ($state->status === TransferStatus::Ready) {
            $this->importData['package'] = $state->finalName;
            Notification::make()->title(__('migration.ui.import.link_ready_title'))->body((string) $state->finalName)->success()->send();
        } elseif ($state->status === TransferStatus::Failed || $state->status === TransferStatus::Cancelled) {
            $text = app(MigrationText::class)->error(MigrationErrorCode::tryFrom((string) ($state->error['code'] ?? '')) ?? MigrationErrorCode::UrlHttpError, (array) ($state->error['params'] ?? []));
            Notification::make()->title(__('migration.ui.import.link_failed_title'))->body(trim($text['message'].' '.$text['fix']))->danger()->persistent()->send();
            $this->transferId = null;
        }
    }

    private function fetchRunning(): bool
    {
        $state = $this->transferId === null ? null : app(TransferManager::class)->find($this->transferId);

        return $state !== null && ! $state->status->isTerminal();
    }

    private function fetchLine(): string
    {
        $state = $this->transferId === null ? null : app(TransferManager::class)->find($this->transferId);

        return $state === null ? '' : __('migration.ui.import.link_progress', ['message' => $state->message]);
    }

    // ---- step 2: open it --------------------------------------------------------------------------------------------------

    private function clientKey(): string
    {
        return 'admin:'.(int) auth('admin')->id().'|'.request()->ip();
    }

    private function openImportPackage(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        $flow = app(AdminImportFlow::class);

        if ($this->importRunId === null) {
            try {
                $opened = $flow->open((string) $this->importData['package'], (string) ($this->importData['passphrase'] ?? ''), $this->initiator(), $this->clientKey());
            } catch (MigrationException $e) {
                $this->failField('importData.passphrase', $e);
            }

            $this->importRunId = $opened['run_id'];
            $this->importToken = $opened['token'];
            $this->importData['passphrase'] = null; // only needed to open the package; the run holds keys, not the passphrase
            $this->importPreflightMemo = null;
        }

        $this->settleOrWait('open', 'importData.passphrase');
    }

    /**
     * Let the run work a little. If it stopped for good, say why and start over; if it is still busy (a big package), keep the
     * person on the page with a progress line that polls, and stay on this step until it says ready.
     */
    private function settleOrWait(string $what, string $field): void
    {
        $result = app(AdminImportFlow::class)->settle((string) $this->importRunId, 15.0);
        $this->importPreflightMemo = null;

        if ($result['failed']) {
            $error = new MigrationException(MigrationErrorCode::tryFrom((string) ($result['error']['code'] ?? '')) ?? MigrationErrorCode::ImportFailedStep, (array) ($result['error']['params'] ?? []));
            $this->dropRun();
            $this->failField($field, $error);
        }

        if (! $result['ready']) {
            $this->importWorking = $what;
            Notification::make()->title(__('migration.ui.import.checking_title'))->body(__('migration.ui.import.checking_body'))->warning()->send();

            throw new Halt;
        }

        $this->importWorking = null;
    }

    /** @param  array{error: array<string, mixed>|null}  $result */
    private function abandonRun(array $result): void
    {
        $text = app(MigrationText::class)->error(MigrationErrorCode::tryFrom((string) ($result['error']['code'] ?? '')) ?? MigrationErrorCode::ImportFailedStep, (array) ($result['error']['params'] ?? []));
        $this->dropRun();
        Notification::make()->title($text['title'])->body(trim($text['message'].' '.$text['fix']))->danger()->persistent()->send();
    }

    /** Forget the run (and throw away what it prepared): nothing on the site was changed by it. */
    private function dropRun(): void
    {
        if ($this->importRunId !== null) {
            app(AdminImportFlow::class)->discard($this->importRunId);
        }

        $this->importRunId = $this->importToken = $this->importWorking = null;
        $this->importGroups = [];
        $this->importEmptied = [];
        $this->importPreflightMemo = null;
    }

    /** @return array<string, mixed> */
    private function initiator(): array
    {
        $admin = auth('admin')->user();

        return ['admin_id' => $admin?->getAuthIdentifier(), 'email' => $admin?->email, 'ip' => request()->ip(), 'via' => 'admin panel'];
    }

    /** @return never */
    private function failField(string $field, MigrationException $e): void
    {
        $text = app(MigrationText::class)->forException($e);

        throw ValidationException::withMessages([$field => trim($text['message'].' '.$text['fix'])]);
    }

    // ---- step 3: what to bring ------------------------------------------------------------------------------------------

    private function summaryText(): string
    {
        if ($this->importRunId === null) {
            return '';
        }

        $summary = app(AdminImportFlow::class)->summary($this->importRunId);

        return __('migration.ui.import.summary', [
            'host' => $summary['source_host'] !== '' ? $summary['source_host'] : __('migration.ui.import.summary_unknown_host'),
            'parts' => implode(', ', array_map(static fn (string $g): string => __('migration.group.'.$g.'.label'), $summary['groups'])),
            'fingerprint' => $summary['fingerprint'],
        ]).($summary['has_secrets'] ? ' '.__('migration.ui.import.summary_secrets') : '');
    }

    private function packageHasSecrets(): bool
    {
        return $this->importRunId !== null && app(AdminImportFlow::class)->summary($this->importRunId)['has_secrets'];
    }

    /** @return list<string> the groups the opened package holds */
    private function heldGroups(): array
    {
        return $this->importRunId === null ? [] : app(AdminImportFlow::class)->summary($this->importRunId)['groups'];
    }

    /** @return array<string, string> */
    private function importPresetOptions(): array
    {
        return ['all' => __('migration.ui.import.preset_all'), 'custom' => __('migration.ui.import.preset_custom')]
            + collect(Preset::cases())->mapWithKeys(fn (Preset $p): array => [$p->value => __($p->labelKey())])->all();
    }

    private function chooseImportGroups(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        $preset = (string) ($this->importData['preset'] ?? 'all');
        $flow = app(AdminImportFlow::class);

        try {
            $choice = $flow->choose(
                (string) $this->importRunId,
                in_array($preset, ['all', 'custom'], true) ? null : $preset,
                $preset === 'custom' ? array_values(array_map('strval', (array) ($this->importData['groups'] ?? []))) : $flow->summary((string) $this->importRunId)['groups'],
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['importData.groups' => __('migration.ui.import.choice_refused', ['detail' => $e->getMessage()])]);
        }

        $this->importGroups = $choice['groups'];

        if ($choice['added'] !== []) {
            Notification::make()->title(__('migration.ui.import.groups_added_title'))->body(__('migration.ui.import.groups_added', ['groups' => implode(', ', array_map(static fn (string $g): string => __('migration.group.'.$g.'.label'), $choice['added']))]))->warning()->send();
        }
    }

    // ---- step 4 and 5: live or test, and the checks ------------------------------------------------------------------------

    private function runImportChecks(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        $address = rtrim(trim((string) $this->importData['site_url']), '/');

        // The address is judged here, with the words of the error catalogue, before it is written into the run: a wrong one found at the
        // last stage would cost a rollback. (ImportTarget is the engine's own rule.)
        try {
            $target = ImportTarget::of(new ImportState('probe', options: ['url' => $address, 'launch' => (string) $this->importData['launch']]));
        } catch (MigrationException $e) {
            $this->failField('importData.site_url', $e);
        }

        // EnforceCanonicalHost works out "https configured" from the address: an https address written from a page opened over
        // plain http would send every later visit to a port nothing answers on, and lock the owner out.
        if (str_starts_with($target->url, 'https://') && ! request()->isSecure()) {
            $this->failField('importData.site_url', new MigrationException(MigrationErrorCode::SiteUrlHttpsUnavailable));
        }

        $options = [
            'launch' => $target->launch,
            'url' => $target->url,
            'include_secrets' => $this->packageHasSecrets() && (bool) ($this->importData['include_secrets'] ?? false),
            'mail_allowlist' => $target->launch === 'staging' && ($this->importData['mail_allowlist'] ?? '') !== '' ? trim((string) $this->importData['mail_allowlist']) : null,
        ];

        $flow = app(AdminImportFlow::class);
        $result = $flow->check((string) $this->importRunId, $options);
        $this->importPreflightMemo = null;

        if ($result['failed']) {
            $this->abandonRun($result);

            throw new Halt;
        }

        if (! $result['ready']) {
            $this->importWorking = 'check';
            Notification::make()->title(__('migration.ui.import.checking_title'))->body(__('migration.ui.import.checking_body'))->warning()->send();

            throw new Halt;
        }

        $this->importWorking = null;
    }

    /** @return array<string, mixed> */
    private function importPreflight(): array
    {
        if ($this->importRunId === null) {
            return ['checks' => [], 'can_proceed' => false, 'required_acks' => [], 'populated' => false, 'business_rows' => []];
        }

        return $this->importPreflightMemo ??= app(AdminImportFlow::class)->preflight($this->importRunId);
    }

    /** @return list<Text> */
    private function checkLines(): array
    {
        $lines = [];

        foreach ($this->importPreflight()['checks'] as $check) {
            [$mark, $icon, $color] = match ($check['status']) {
                'fail' => [__('migration.ui.import.mark_fail'), 'heroicon-o-x-circle', 'danger'],
                'warn' => [__('migration.ui.import.mark_warn'), 'heroicon-o-exclamation-triangle', 'warning'],
                default => [__('migration.ui.import.mark_pass'), 'heroicon-o-check-circle', 'success'],
            };

            $lines[] = Text::make(__('migration.ui.import.check_line', ['mark' => $mark, 'label' => $check['label'], 'message' => $check['message']]))->icon($icon)->color($color);
        }

        return $lines;
    }

    private function importCanProceed(): bool
    {
        return (bool) $this->importPreflight()['can_proceed'];
    }

    private function importPopulated(): bool
    {
        return (bool) $this->importPreflight()['populated'];
    }

    /** "1,234 orders and payments, 56 customers": the business rows of a used site, grouped as people know them. */
    private function populatedCounts(): string
    {
        $registry = app(GroupRegistry::class);
        $parts = [];

        foreach ((array) $this->importPreflight()['business_rows'] as $table => $rows) {
            $owner = $registry->ownerOf((string) $table)?->value ?? 'core';
            $parts[$owner] = ($parts[$owner] ?? 0) + (int) $rows;
        }

        return implode(', ', array_map(static fn (string $group, int $rows): string => number_format($rows).' '.mb_strtolower(__('migration.group.'.$group.'.label')), array_keys($parts), $parts));
    }

    /** @return array<string, string> acknowledgement key => the warning it stands for */
    private function ackOptions(): array
    {
        $preflight = $this->importPreflight();
        $options = [];

        foreach ($preflight['required_acks'] as $ack) {
            $options[$ack] = $ack;

            foreach ($preflight['checks'] as $check) {
                if ($check['ack'] === $ack) {
                    $options[$ack] = $check['label'].': '.$check['message'];
                }
            }
        }

        return $options;
    }

    private function requireImportReady(): void
    {
        if (! $this->importCanProceed()) {
            Notification::make()->title(__('migration.ui.import.blocked_title'))->body(__('migration.ui.import.blocked_text'))->danger()->send();

            throw new Halt;
        }

        // What this site holds now, counted once here (a count of every table is not something to repeat on each redraw).
        $this->importEmptied = app(AdminImportFlow::class)->rowsPerPart();
    }

    /** @return list<Text> */
    private function emptiedLines(): array
    {
        return array_map(
            static fn (string $group, int $rows): Text => Text::make(__('migration.ui.import.emptied_line', ['part' => __('migration.group.'.$group.'.label'), 'rows' => number_format($rows)])),
            array_keys($this->importEmptied),
            $this->importEmptied,
        );
    }

    // ---- step 6: the deliberate act -------------------------------------------------------------------------------------

    public function confirmImport(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        if ($this->importRunId === null || $this->importToken === null) {
            Notification::make()->title(__('migration.ui.import.refused_title'))->body(__('migration.ui.import.not_ready'))->danger()->send();

            return;
        }

        $data = $this->validatedImportState();
        $this->confirmPassword($data, 'importData.password');

        try {
            app(AdminImportFlow::class)->confirm(
                $this->importRunId,
                array_values(array_map('strval', (array) ($data['acks'] ?? []))),
                (string) ($data['typed'] ?? ''),
                $this->importGroups,
            );
        } catch (MigrationException $e) {
            $text = app(MigrationText::class)->forException($e);
            Notification::make()->title($text['title'] !== '' ? $text['title'] : __('migration.ui.import.refused_title'))->body(trim($text['message'].' '.$text['fix']))->danger()->persistent()->send();

            return;
        }

        $this->recordConfirmation();

        $url = url('/migrate/run/'.$this->importRunId).'#t='.$this->importToken;
        $this->importToken = null;
        $this->importData['typed'] = $this->importData['password'] = null;

        $this->redirect($url);
    }

    /** The durable line (log channel and history file) that an admin typed REPLACE and gave the password. The engine adds its own at the swap. */
    private function recordConfirmation(): void
    {
        try {
            app(PackageAudit::class)->record('import.confirmed', (string) $this->importRunId, ['by_admin' => auth('admin')->id(), 'door' => 'admin panel', 'groups' => $this->importGroups]);
        } catch (\Throwable $e) {
            Log::channel('migration')->warning('The line for the confirmation of import '.$this->importRunId.' could not be written: '.$e::class);
        }
    }

    /**
     * The whole wizard's answers, validated. A field on an EARLIER step can be wrong (the person is looking at the last one), so
     * the first complaint is also said out loud.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatedImportState(): array
    {
        try {
            return $this->getSchema('importForm')->getState();
        } catch (ValidationException $e) {
            Notification::make()->title(__('migration.ui.import.check_steps_title'))->body((string) collect($e->errors())->flatten()->first())->danger()->send();

            throw $e;
        }
    }

    public function cancelImport(): void
    {
        abort_unless(MigrationAccess::canImport(), 403);

        if ($this->transferId !== null) {
            try {
                app(TransferManager::class)->cancel($this->transferId);
            } catch (\Throwable $e) {
                Log::channel('migration')->warning('A download could not be cancelled when the import was started over: '.$e::class);
            }
        }

        $this->dropRun();
        $this->transferId = null;
        $this->getSchema('importForm')->fill($this->importDefaults());
        $this->getSchema('importForm')->dispatchClientSideStateReset();
        Notification::make()->title(__('migration.ui.import.cancelled_title'))->success()->send();
    }
}
