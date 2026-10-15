<?php

namespace App\Filament\Pages\System;

use App\Filament\Pages\System\Concerns\HasExportWizard;
use App\Filament\Pages\System\Concerns\HasImportWizard;
use App\Filament\Widgets\Migration\MigrationHistoryTable;
use App\Filament\Widgets\Migration\MigrationPackagesTable;
use App\Filament\Widgets\Migration\MigrationShareLinksTable;
use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\MigrationQueues;
use App\Services\Migration\Transport\TransferLimits;
use App\Services\Migration\Ux\MigrationAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

/**
 * System → Migration: move a whole shop to another server with one .oeparts file. Built only from stock Filament parts
 * (tabs, tables, wizard, sections, callouts, actions) so it follows the panel's look, dark mode and upgrades.
 *
 * Tabs: Export (a five-step wizard and the run it starts), Import (a six-step wizard that prepares a run and hands over to the
 * runner page), Packages (what this site has exported), History (every migration event, from the audit file that survives a
 * database swap) and Limits (the numbers that apply). All wording is in lang/en/migration.php under `ui.*`.
 *
 * The page never touches a file itself: it names a package by FILE NAME and the services decide what that means.
 */
class MigrationDashboard extends Page
{
    use HasExportWizard;
    use HasImportWizard;

    protected static ?string $slug = 'system/migration';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 42;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function canAccess(): bool
    {
        return MigrationAccess::canOpen();
    }

    public function getTitle(): string
    {
        return __('migration.ui.page.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('migration.ui.page.title');
    }

    public function getSubheading(): ?string
    {
        return __('migration.ui.page.subheading');
    }

    public function mount(): void
    {
        $this->mountHasExportWizard();
        $this->mountHasImportWizard();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkWorker')
                ->label(__('migration.ui.page.check_worker'))
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->authorize(fn () => MigrationAccess::canOpen())
                ->tooltip(__('migration.ui.page.check_worker_tip'))
                ->action(fn () => $this->checkWorker()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(__('migration.ui.page.no_worker_title'))
                ->description(__('migration.ui.page.no_worker_text'))
                ->icon('heroicon-o-information-circle')
                ->info()
                ->visible(fn (): bool => config('queue.default') === 'sync'),

            Tabs::make('Migration')
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs([
                    Tab::make(__('migration.ui.tabs.export'))
                        ->id('export')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->visible(fn (): bool => MigrationAccess::canExport())
                        ->schema([
                            $this->exportRunSection(),
                            EmbeddedSchema::make('exportForm'),
                        ]),
                    Tab::make(__('migration.ui.tabs.import'))
                        ->id('import')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (): bool => MigrationAccess::canImport())
                        ->schema($this->importTabSchema()),
                    Tab::make(__('migration.ui.tabs.packages'))
                        ->id('packages')
                        ->icon('heroicon-o-archive-box')
                        ->visible(fn (): bool => MigrationAccess::canExport())
                        ->schema([Livewire::make(MigrationPackagesTable::class)]),
                    Tab::make(__('migration.ui.tabs.share_links'))
                        ->id('share-links')
                        ->icon('heroicon-o-link')
                        ->visible(fn (): bool => MigrationAccess::canExport())
                        ->schema([Livewire::make(MigrationShareLinksTable::class)]),
                    Tab::make(__('migration.ui.tabs.history'))
                        ->id('history')
                        ->icon('heroicon-o-clock')
                        ->schema([Livewire::make(MigrationHistoryTable::class)]),
                    Tab::make(__('migration.ui.tabs.limits'))
                        ->id('limits')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema($this->limitsSchema()),
                ]),
        ]);
    }

    /** @return list<Section> */
    private function limitsSchema(): array
    {
        $limits = app(TransferLimits::class);
        $line = static fn (string $label, string $value): Text => Text::make($label.': '.$value);

        return [
            Section::make(__('migration.ui.limits.from_link.title'))
                ->description(__('migration.ui.limits.from_link.description'))
                ->schema([
                    $line(__('migration.ui.limits.from_link.max_size'), Number::fileSize($limits->maxBytes())),
                    Text::make(fn (): string => __('migration.ui.limits.from_link.free_room').': '.($limits->freeBytes() === null ? __('migration.ui.limits.unknown') : Number::fileSize((int) $limits->freeBytes()))),
                    $line(__('migration.ui.limits.from_link.per_request'), Number::fileSize($limits->rangeBytes())),
                    $line(__('migration.ui.limits.from_link.attempts'), (string) $limits->maxAttempts()),
                    $line(__('migration.ui.limits.from_link.private'), __('migration.ui.limits.from_link.'.((bool) config('migration.transport.allow_private_hosts') ? 'private_allowed' : 'private_refused'))),
                ]),
            Section::make(__('migration.ui.limits.hand_out.title'))
                ->description(__('migration.ui.limits.hand_out.description'))
                ->schema([
                    $line(__('migration.ui.limits.hand_out.lifetime'), __('migration.ui.limits.hand_out.lifetime_value', ['hours' => config('migration.share.ttl_hours'), 'max' => config('migration.share.max_ttl_hours')])),
                    $line(__('migration.ui.limits.hand_out.max_live'), (string) config('migration.share.max_active')),
                    $line(__('migration.ui.limits.hand_out.streams'), __('migration.ui.limits.hand_out.streams_value', ['count' => max(1, (int) config('migration.share.max_streams'))])),
                    $line(__('migration.ui.limits.hand_out.refusals'), (string) config('migration.share.deny_limit_per_hour')),
                    $line(__('migration.ui.limits.hand_out.kept'), __('migration.ui.limits.hand_out.kept_value', ['days' => config('migration.retention.export_days')])),
                ]),
            Section::make(__('migration.ui.limits.replace.title'))
                ->description(__('migration.ui.limits.replace.description'))
                ->schema([
                    $line(__('migration.ui.limits.replace.recovery_rows'), number_format((int) config('migration.import.recovery_max_rows'))),
                    Text::make(fn (): string => __('migration.ui.limits.replace.worker').': '.__('migration.ui.limits.replace.'.(app(MigrationWorkerProbe::class)->isFresh() ? 'worker_proven' : 'worker_unproven'))),
                ]),
        ];
    }

    private function checkWorker(): void
    {
        abort_unless(MigrationAccess::canOpen(), 403);

        if (config('queue.default') === 'sync') {
            Notification::make()->title(__('migration.ui.worker.nothing_title'))->body(__('migration.ui.worker.nothing_body'))->warning()->send();

            return;
        }

        if (app(MigrationWorkerProbe::class)->runAndWait(15)) {
            Notification::make()->title(__('migration.ui.worker.answered_title'))->body(__('migration.ui.worker.answered_body'))->success()->send();

            return;
        }

        $line = '--queue='.implode(',', [MigrationQueues::migration(), ...MigrationQueues::customer()]);
        Notification::make()
            ->title(__('migration.ui.worker.silent_title'))
            ->body(__('migration.ui.worker.silent_body', ['line' => $line]))
            ->warning()
            ->persistent()
            ->send();
    }
}
