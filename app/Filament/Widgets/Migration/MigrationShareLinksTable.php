<?php

namespace App\Filament\Widgets\Migration;

use App\Filament\Widgets\Migration\Concerns\ConfirmsPassword;
use App\Models\PackageShareLink;
use App\Services\Migration\Transport\ShareLinkService;
use App\Services\Migration\Ux\MigrationAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * The one-time download links this site has made, with the state each is in, and the way to switch one off.
 *
 * Making a link stays on the Packages tab (its "Share link" action shows the link ONCE, with the command for the other site, and
 * says the passphrase must travel by another route): the link's secret is never stored, only a fingerprint of it, so it cannot be
 * shown again here. Nothing on this list can be used to download anything. The package is named by its file name only, never a
 * path, and revoking asks for the admin's password again (ReauthGate: five wrong tries lock it for ten minutes).
 *
 * Both listing and revoking need the permission to export: a link hands out a full copy of the shop's data.
 */
class MigrationShareLinksTable extends TableWidget
{
    use ConfirmsPassword;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    private const SHOWN = 200;

    public static function canView(): bool
    {
        return MigrationAccess::canExport();
    }

    /** A link was made on the Packages tab: this list is shown again. */
    #[On('migration-changed')]
    public function refreshList(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('migration.ui.share.heading'))
            ->description(__('migration.ui.share.description').' '.__('migration.ui.packages.share_text'))
            ->paginated(false)
            ->columns([
                TextColumn::make('package')->label(__('migration.ui.share.col_package'))->fontFamily(FontFamily::Mono)->wrap(),
                TextColumn::make('created_at')->label(__('migration.ui.share.col_created'))
                    ->state(fn (array $record): string => Carbon::parse($record['created_at'])->diffForHumans())
                    ->tooltip(fn (array $record): string => Carbon::parse($record['created_at'])->toDateTimeString()),
                TextColumn::make('expires_at')->label(__('migration.ui.share.col_expires'))
                    ->state(fn (array $record): string => $record['state'] === 'active' ? Carbon::parse($record['expires_at'])->diffForHumans() : '—')
                    ->tooltip(fn (array $record): string => Carbon::parse($record['expires_at'])->toDateTimeString()),
                TextColumn::make('state')->label(__('migration.ui.share.col_state'))->badge()
                    ->state(fn (array $record): string => __('migration.ui.share.state_'.$record['state']))
                    ->color(fn (array $record): string => match ($record['state']) {
                        'active' => 'success',
                        'used' => 'info',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('uses')->label(__('migration.ui.share.col_uses'))->alignEnd()->state(fn (array $record): int => $record['uses']),
                TextColumn::make('refused')->label(__('migration.ui.share.col_refused'))->alignEnd()->state(fn (array $record): int => $record['refused']),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('migration.ui.share.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (array $record): bool => MigrationAccess::canExport() && $record['state'] === 'active')
                    ->modalHeading(__('migration.ui.share.revoke_heading'))
                    ->modalDescription(__('migration.ui.share.revoke_text'))
                    ->modalSubmitActionLabel(__('migration.ui.share.revoke'))
                    ->schema([$this->passwordField()])
                    ->action(fn (array $record, array $data) => $this->revoke((int) $record['id'], $data)),
            ])
            ->emptyStateHeading(__('migration.ui.share.empty_heading'))
            ->emptyStateDescription(__('migration.ui.share.empty_text'))
            ->emptyStateIcon('heroicon-o-link');
    }

    /**
     * Newest first, keyed by the link's id. The package is its file name only (whatever the row holds, a path never reaches the
     * page); no field of a link that could open the download (the token's fingerprint, the claim secret) is read at all.
     *
     * @return array<int, array<string, mixed>> keyed by the link's id (PHP turns the numeric key into an int)
     */
    private function records(): array
    {
        if (! MigrationAccess::canExport()) {
            return [];
        }

        $rows = [];

        foreach (PackageShareLink::query()->latest('id')->limit(self::SHOWN)->get(['id', 'package_name', 'state', 'expires_at', 'claim_expires_at', 'first_claimed_at', 'created_at', 'deny_count']) as $link) {
            $rows[(string) $link->id] = [
                'id' => (int) $link->id,
                'package' => basename(str_replace('\\', '/', (string) $link->package_name)),
                'state' => $this->stateOf($link),
                'created_at' => $link->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'expires_at' => ($link->state === PackageShareLink::CLAIMED && $link->claim_expires_at !== null ? $link->claim_expires_at : $link->expires_at)->toIso8601String(),
                'uses' => $link->first_claimed_at !== null ? 1 : 0,
                'refused' => (int) $link->deny_count,
            ];
        }

        return $rows;
    }

    /** active | used | expired | revoked, from the row and the clock (a late link is shown as over without writing anything). */
    private function stateOf(PackageShareLink $link): string
    {
        return match ($link->state) {
            PackageShareLink::REVOKED => 'revoked',
            PackageShareLink::CONSUMED => 'used',
            PackageShareLink::EXPIRED => 'expired',
            PackageShareLink::CLAIMED => $link->claim_expires_at !== null && $link->claim_expires_at->isFuture() ? 'active' : 'expired',
            default => $link->expires_at->isFuture() ? 'active' : 'expired',
        };
    }

    /** @param  array<string, mixed>  $data */
    private function revoke(int $id, array $data): void
    {
        abort_unless(MigrationAccess::canExport(), 403);
        $this->confirmPassword($data);

        if (app(ShareLinkService::class)->revoke($id)) {
            Notification::make()->title(__('migration.ui.share.revoked_title'))->body(__('migration.ui.share.revoked_body'))->success()->send();
        } else {
            Notification::make()->title(__('migration.ui.share.already_closed_title'))->warning()->send();
        }

        $this->dispatch('migration-changed');
    }
}
