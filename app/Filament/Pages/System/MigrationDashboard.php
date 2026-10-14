<?php

namespace App\Filament\Pages\System;

use App\Filament\Widgets\Migration\MigrationHistoryTable;
use App\Filament\Widgets\Migration\MigrationPackagesTable;
use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\MigrationQueues;
use App\Services\Migration\Transport\TransferLimits;
use App\Services\Migration\Ux\MigrationAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

/**
 * System → Migration: move a whole shop to another server with one .oeparts file. Built only from stock Filament parts
 * (tabs, tables, sections, callouts, actions) so it follows the panel's look, dark mode and upgrades.
 *
 * Tabs: Packages (what this site has exported), History (every migration event, from the audit file that survives a database
 * swap) and Limits (the numbers that apply). Export, Import and Share links join them in their own steps.
 *
 * The page never touches a file itself: it names a package by FILE NAME and the services decide what that means.
 */
class MigrationDashboard extends Page
{
    protected static ?string $slug = 'system/migration';

    protected static ?string $title = 'Migration';

    protected ?string $subheading = 'Move this shop to another server, or bring a shop in, with one encrypted .oeparts file. Everything here is checked before it changes anything, and an import can always be taken back.';

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkWorker')
                ->label('Check worker')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->authorize(fn () => MigrationAccess::canOpen())
                ->tooltip('Asks the queue worker to prove it also serves the migration queue (needed for a freeze export started here)')
                ->action(fn () => $this->checkWorker()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('No queue worker is set up on this server')
                ->description('Exports and imports still work from the command line, and an export that keeps the shop running is driven from this page while it is open. A "freeze, then copy" export needs a worker that also processes the migration queue.')
                ->icon('heroicon-o-information-circle')
                ->info()
                ->visible(fn (): bool => config('queue.default') === 'sync'),

            Tabs::make('Migration')
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs([
                    Tab::make('Packages')
                        ->icon('heroicon-o-archive-box')
                        ->schema([Livewire::make(MigrationPackagesTable::class)]),
                    Tab::make('History')
                        ->icon('heroicon-o-clock')
                        ->schema([Livewire::make(MigrationHistoryTable::class)]),
                    Tab::make('Limits')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema($this->limitsSchema()),
                ]),
        ]);
    }

    /** @return list<Section> */
    private function limitsSchema(): array
    {
        $line = static fn (string $label, string $value): Text => Text::make($label.': '.$value);
        $limits = fn (): array => app(TransferLimits::class)->describe();

        return [
            Section::make('Getting a package from a link')
                ->description('What applies when this site downloads a package from another site.')
                ->schema([
                    $line('Largest package accepted', Number::fileSize(app(TransferLimits::class)->maxBytes())),
                    Text::make(fn (): string => 'Free room for downloads: '.($limits()['free_bytes'] === null ? 'unknown' : Number::fileSize((int) $limits()['free_bytes']))),
                    $line('Bytes asked for per request', Number::fileSize(app(TransferLimits::class)->rangeBytes())),
                    $line('Attempts before giving up', (string) app(TransferLimits::class)->maxAttempts()),
                    $line('Links to private or local addresses', (bool) config('migration.transport.allow_private_hosts') ? 'allowed (set in .env)' : 'refused'),
                ]),
            Section::make('Handing a package out')
                ->description('What applies when another site downloads a package from this one.')
                ->schema([
                    $line('Link lifetime', config('migration.share.ttl_hours').' hours (at most '.config('migration.share.max_ttl_hours').')'),
                    $line('Links that can be live at once', (string) config('migration.share.max_active')),
                    $line('Downloads at the same time', (string) max(1, (int) config('migration.share.max_streams')).' (raise OE_SHARE_MAX_STREAMS only if the web server has many workers)'),
                    $line('Refused tries per address and hour', (string) config('migration.share.deny_limit_per_hour')),
                    $line('Exported packages are kept', config('migration.retention.export_days').' days'),
                ]),
            Section::make('Replacing a site')
                ->description('What applies when a package is imported into this site.')
                ->schema([
                    $line('Rows a recovery point may hold', number_format((int) config('migration.import.recovery_max_rows'))),
                    $line('Rows that still count as a new, unused site', number_format((int) config('migration.import.fresh_max_rows'))),
                    Text::make(fn (): string => 'Queue worker for the migration queue: '.(app(MigrationWorkerProbe::class)->isFresh() ? 'proven recently' : 'not proven (press "Check worker")')),
                ]),
        ];
    }

    private function checkWorker(): void
    {
        abort_unless(MigrationAccess::canOpen(), 403);

        $probe = app(MigrationWorkerProbe::class);

        if (config('queue.default') === 'sync') {
            Notification::make()->title('Nothing to check')->body('This server runs jobs inline (QUEUE_CONNECTION=sync), so there is no worker to ask.')->warning()->send();

            return;
        }

        if ($probe->runAndWait(15)) {
            Notification::make()->title('A worker answered')->body('A queue worker is serving the migration queue.')->success()->send();

            return;
        }

        $line = '--queue='.implode(',', [MigrationQueues::migration(), ...MigrationQueues::customer()]);
        Notification::make()
            ->title('No worker answered in 15 seconds')
            ->body("If you run a worker, add the migration queue to its command and restart it: php artisan queue:work {$line}")
            ->warning()
            ->persistent()
            ->send();
    }
}
