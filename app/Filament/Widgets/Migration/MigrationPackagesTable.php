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
        return MigrationAccess::canExport();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('migration.ui.packages.heading'))
            ->description(__('migration.ui.packages.description', ['days' => (int) config('migration.retention.export_days', 7)]))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label(__('migration.ui.packages.col_package'))->fontFamily(FontFamily::Mono)->wrap(),
                TextColumn::make('size')->label(__('migration.ui.packages.col_size'))->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('modified')->label(__('migration.ui.packages.col_made'))
                    ->formatStateUsing(fn (int $state): string => Carbon::createFromTimestamp($state)->diffForHumans())
                    ->tooltip(fn (array $record): string => Carbon::createFromTimestamp($record['modified'])->toDateTimeString()),
                TextColumn::make('expires_at')->label(__('migration.ui.packages.col_removed'))
                    ->state(fn (array $record): string => $record['expires_at'] === null ? '—' : Carbon::parse($record['expires_at'])->diffForHumans()),
                TextColumn::make('contents')->label(__('migration.ui.packages.col_contents'))->state(fn (array $record): string => $record['contents'])->wrap(),
                TextColumn::make('flags')->label('')->badge()
                    ->state(fn (array $record): array => $record['flags'])
                    ->color(fn (string $state): string => $state === __('migration.ui.packages.flag_secrets') ? 'warning' : 'gray'),
                TextColumn::make('links')->label(__('migration.ui.packages.col_links'))->alignEnd()
                    ->state(fn (array $record): int => $record['links']),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('migration.ui.packages.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => MigrationAccess::canExport())
                    ->modalHeading(__('migration.ui.packages.download_heading'))
                    ->modalDescription(__('migration.ui.packages.download_text'))
                    ->modalSubmitActionLabel(__('migration.ui.packages.download'))
                    ->schema([$this->passwordField()])
                    ->action(function (array $record, array $data) {
                        abort_unless(MigrationAccess::canExport(), 403);
                        $this->confirmPassword($data);

                        return redirect()->to(MigrationPackageDownloadController::url((string) $record['name']));
                    }),
                Action::make('share')
                    ->label(__('migration.ui.packages.share'))
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(fn (): bool => MigrationAccess::canExport())
                    ->modalHeading(__('migration.ui.packages.share_heading'))
                    ->modalDescription(__('migration.ui.packages.share_text'))
                    ->modalSubmitActionLabel(__('migration.ui.packages.share_submit'))
                    ->schema([
                        TextInput::make('hours')->label(__('migration.ui.packages.share_hours'))->numeric()->minValue(1)->maxValue((int) config('migration.share.max_ttl_hours', 168))->default((int) config('migration.share.ttl_hours', 24))->required(),
                        TextInput::make('ip')->label(__('migration.ui.packages.share_ip'))->maxLength(45),
                        $this->passwordField(),
                    ])
                    ->action(fn (array $record, array $data) => $this->makeLink((string) $record['name'], $data)),
                Action::make('delete')
                    ->label(__('migration.ui.packages.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (): bool => MigrationAccess::canExport())
                    ->modalHeading(__('migration.ui.packages.delete_heading'))
                    ->modalDescription(__('migration.ui.packages.delete_text'))
                    ->schema([$this->passwordField()])
                    ->action(fn (array $record, array $data) => $this->deletePackage((string) $record['name'], $data)),
            ])
            ->emptyStateHeading(__('migration.ui.packages.empty_heading'))
            ->emptyStateDescription(__('migration.ui.packages.empty_text'))
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
            ! empty($info['secrets_included']) && $flags[] = __('migration.ui.packages.flag_secrets');
            ! empty($info['anonymised']) && $flags[] = __('migration.ui.packages.flag_scrambled');
            isset($info['consistency']) && $flags[] = __('migration.ui.packages.'.($info['consistency'] === 'frozen' ? 'flag_frozen' : 'flag_live'));

            $rows[$package['name']] = [
                'name' => $package['name'],
                'size' => $package['size'],
                'modified' => $package['modified'],
                'expires_at' => $info['expires_at'] ?? null,
                'contents' => isset($info['preset']) && $info['preset'] ? __('migration.preset.'.$info['preset'].'.label') : __('migration.ui.packages.parts_count', ['count' => count((array) ($info['groups'] ?? []))]),
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

        $body = __('migration.ui.packages.link_body', ['share' => $made['share']]);
        $made['warning'] !== null && $body .= "\n\n".$made['warning'];

        Notification::make()->title(__('migration.ui.packages.link_title'))->body($body)->success()->persistent()->send();
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
            Notification::make()->title(__('migration.ui.packages.in_use_title'))->body(__('migration.ui.packages.in_use_body'))->warning()->send();

            return;
        }

        if (app(PackageOutbox::class)->delete($name)) {
            try {
                app(PackageAudit::class)->record('package.deleted', null, ['package' => $name, 'by_admin' => auth('admin')->id()]);
            } catch (\Throwable) {
                // the file is gone either way; the history line is best effort
            }

            Notification::make()->title(__('migration.ui.packages.deleted_title'))->success()->send();
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
