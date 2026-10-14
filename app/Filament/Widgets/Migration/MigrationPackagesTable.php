<?php

namespace App\Filament\Widgets\Migration;

use App\Filament\Pages\System\MigrationDashboard;
use App\Filament\Widgets\Migration\Concerns\ConfirmsPassword;
use App\Http\Controllers\Admin\MigrationPackageDownloadController;
use App\Models\PackageShareLink;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\ShareLinkService;
use App\Services\Migration\Ux\MigrationAccess;
use App\Services\Migration\Ux\MigrationText;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * The packages this site has exported and still holds. A package is a full copy of the shop's data, so every way of getting it
 * out (download, share link) and of removing it asks for the admin's password again, and the file is only ever named by its
 * file name inside the exports folder.
 */
class MigrationPackagesTable extends TableWidget
{
    use ConfirmsPassword;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return MigrationDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        $days = (int) config('migration.retention.export_days', 7);

        return $table
            ->records(fn (): array => $this->records())
            ->heading('Exported packages')
            ->description("Packages made on this site, newest first. Each is kept for {$days} days and then removed. A package holds all the data you chose, so treat it like a backup.")
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Package')->fontFamily(FontFamily::Mono)->wrap(),
                TextColumn::make('size')->label('Size')->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('modified')->label('Made')
                    ->formatStateUsing(fn (int $state): string => Carbon::createFromTimestamp($state)->diffForHumans())
                    ->tooltip(fn (array $record): string => Carbon::createFromTimestamp($record['modified'])->toDateTimeString()),
                TextColumn::make('expires_at')->label('Removed')
                    ->state(fn (array $record): string => $record['expires_at'] === null ? '—' : Carbon::parse($record['expires_at'])->diffForHumans()),
                TextColumn::make('contents')->label('What is in it')->state(fn (array $record): string => $record['contents'])->wrap(),
                TextColumn::make('flags')->label('')->badge()
                    ->state(fn (array $record): array => $record['flags'])
                    ->color(fn (string $state): string => $state === 'Includes secrets' ? 'warning' : 'gray'),
                TextColumn::make('links')->label('Live links')->alignEnd()
                    ->state(fn (array $record): int => $record['links']),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => MigrationAccess::canExport())
                    ->modalHeading('Download this package')
                    ->modalDescription('Confirm your password. The download starts in a moment and can be resumed if it is interrupted.')
                    ->modalSubmitActionLabel('Download')
                    ->schema([$this->passwordField()])
                    ->action(function (array $record, array $data) {
                        abort_unless(MigrationAccess::canExport(), 403);
                        $this->confirmPassword($data);

                        return redirect()->to(MigrationPackageDownloadController::url((string) $record['name']));
                    }),
                Action::make('share')
                    ->label('Share link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (): bool => MigrationAccess::canExport())
                    ->modalHeading('Make a one-time download link')
                    ->modalDescription('The other site pulls the package straight from this one with this link. It works once, and the passphrase is NOT part of it: send the passphrase by a different route than the link.')
                    ->modalSubmitActionLabel('Make the link')
                    ->schema([
                        TextInput::make('hours')->label('Valid for (hours)')->numeric()->minValue(1)->maxValue((int) config('migration.share.max_ttl_hours', 168))->default((int) config('migration.share.ttl_hours', 24))->required(),
                        TextInput::make('ip')->label('Only this address may download (optional)')->maxLength(45),
                        $this->passwordField(),
                    ])
                    ->action(fn (array $record, array $data) => $this->makeLink((string) $record['name'], $data)),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => MigrationAccess::canExport())
                    ->modalHeading('Delete this package?')
                    ->modalDescription('The file is removed from this server. Links made for it stop working. This cannot be undone.')
                    ->schema([$this->passwordField()])
                    ->action(fn (array $record, array $data) => $this->deletePackage((string) $record['name'], $data)),
            ])
            ->emptyStateHeading('No exported packages')
            ->emptyStateDescription('Start an export on the Export tab, or run: php artisan oeparts:migrate:export')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    /** @return array<string, array<string, mixed>> keyed by the package file name */
    private function records(): array
    {
        $live = PackageShareLink::query()->whereIn('state', [PackageShareLink::ACTIVE, PackageShareLink::CLAIMED])
            ->where('expires_at', '>', now())->selectRaw('package_name, count(*) as n')->groupBy('package_name')->pluck('n', 'package_name');
        $rows = [];

        foreach (app(PackageOutbox::class)->list() as $package) {
            $info = $package['info'];
            $flags = [];
            ! empty($info['secrets_included']) && $flags[] = 'Includes secrets';
            ! empty($info['anonymised']) && $flags[] = 'Customers scrambled';
            isset($info['consistency']) && $flags[] = $info['consistency'] === 'frozen' ? 'Frozen copy' : 'Live copy';

            $rows[$package['name']] = [
                'name' => $package['name'],
                'size' => $package['size'],
                'modified' => $package['modified'],
                'expires_at' => $info['expires_at'] ?? null,
                'contents' => isset($info['preset']) && $info['preset'] ? str_replace('_', ' ', (string) $info['preset']) : count((array) ($info['groups'] ?? [])).' part(s)',
                'flags' => $flags,
                'links' => (int) ($live[$package['name']] ?? 0),
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $data */
    private function makeLink(string $name, array $data): void
    {
        abort_unless(MigrationAccess::canExport(), 403);
        $this->confirmPassword($data);

        try {
            $made = app(ShareLinkService::class)->create($name, (int) auth('admin')->id(), (int) ($data['hours'] ?? 24), ($data['ip'] ?? '') !== '' ? (string) $data['ip'] : null);
        } catch (MigrationException $e) {
            $this->refuse($e);

            return;
        }

        $note = Notification::make()
            ->title('One-time link made')
            ->body($made['share']."\n\nShown once. It works once, and the passphrase is not part of it: send that separately.\nOn the other site: php artisan oeparts:migrate:import --url='<this link>'")
            ->success()
            ->persistent();

        $made['warning'] !== null && $note->body($note->getBody()."\n\n".$made['warning']);
        $note->send();
        $this->dispatch('migration-changed');
    }

    /** @param  array<string, mixed>  $data */
    private function deletePackage(string $name, array $data): void
    {
        abort_unless(MigrationAccess::canExport(), 403);
        $this->confirmPassword($data);

        $inUse = PackageShareLink::query()->where('package_name', $name)->whereIn('state', [PackageShareLink::ACTIVE, PackageShareLink::CLAIMED])->where('expires_at', '>', now())->exists()
            || $this->usedByARun($name);

        if ($inUse) {
            Notification::make()->title('This package is in use')->body('A live share link or a running import uses it. Revoke the link or wait for the run to end first.')->warning()->send();

            return;
        }

        if (app(PackageOutbox::class)->delete($name)) {
            try {
                app(PackageAudit::class)->record('package.deleted', null, ['package' => $name, 'by_admin' => auth('admin')->id()]);
            } catch (\Throwable) {
                // the file is gone either way; the history line is best effort
            }

            Notification::make()->title('Package deleted')->success()->send();
        }

        $this->dispatch('migration-changed');
    }

    private function usedByARun(string $name): bool
    {
        $active = app(ImportStateStore::class)->active();

        return $active !== null && basename((string) ($active->package['path'] ?? '')) === $name;
    }

    private function refuse(MigrationException $e): void
    {
        $text = app(MigrationText::class)->forException($e);
        Notification::make()->title($text['title'])->body($text['message'].' '.$text['fix'])->danger()->send();
    }
}
