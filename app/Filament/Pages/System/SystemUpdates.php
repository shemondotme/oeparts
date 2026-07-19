<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Models\UpdateHistory;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\UpdateChecker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Update & Recovery System (Module 21) — the "System Updates" page.
 *
 * Shows the installed vs latest version, the changelog, and highlights security
 * releases (Chunk 1.3). One-click apply (Chunk 3.5, startApply()/pollApply())
 * runs the full backup → download → swap → migrate → verify FSM; the changelog/
 * download links remain as a manual fallback for an admin without the
 * "apply updates" permission. Lazy-tier check runs on mount() (cache-backed);
 * "Check now" forces a fresh check.
 */
class SystemUpdates extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'System Updates';

    protected string $view = 'filament.pages.system.system-updates';

    /** @var array<string,mixed>|null Result of UpdateChecker::check()->toArray() */
    public ?array $status = null;

    /* ---- One-click apply (Chunk 3.5) ---- */
    public bool $applying = false;
    public ?int $applyHistoryId = null;
    /** @var array<string,mixed>|null current FSM status for the progress panel */
    public ?array $applyStatus = null;
    public ?string $applyPassword = null;

    /** @var array<string,mixed> UpdatePreview::toArray() once loaded — drives the confirm panel. */
    public array $applyPreview = [];
    /** Operator has explicitly acknowledged the preflight WARNs shown in the preview. */
    public bool $previewAcknowledged = false;

    public function mount(): void
    {
        // Lazy tier — served from cache unless the TTL has expired.
        $this->status = app(UpdateChecker::class)->check()->toArray();

        // Resume a running apply if the operator reloaded mid-update.
        $running = UpdateHistory::query()->whereNotIn('status', [
            UpdateHistory::STATUS_SUCCESS, UpdateHistory::STATUS_FAILED, UpdateHistory::STATUS_ROLLED_BACK,
        ])->recent()->first();
        if ($running) {
            $this->applying = true;
            $this->applyHistoryId = $running->id;
            $this->applyStatus = ['status' => $running->status, 'step' => $running->step];
        }
    }

    public function canApply(): bool
    {
        return (bool) auth('admin')->user()?->can('apply updates');
    }

    /** Load the "what will happen" preview (version jump, size, migrations, preflight) into the confirm panel. */
    public function loadPreview(): void
    {
        abort_unless($this->canApply(), 403);

        $manifest = $this->applyManifest();
        if (! $manifest) {
            Notification::make()->title('No update to apply')->warning()->send();

            return;
        }

        $this->applyPreview = app(UpdateApplier::class)->preview($manifest)->toArray();
        $this->previewAcknowledged = false;
    }

    /** Back out of the preview panel without starting anything. */
    public function cancelPreview(): void
    {
        $this->applyPreview = [];
        $this->previewAcknowledged = false;
        $this->applyPassword = null;
    }

    /** Re-auth (password) then start the resumable apply FSM. */
    public function startApply(): void
    {
        abort_unless($this->canApply(), 403);

        $admin = auth('admin')->user();
        if (! $admin || ! Hash::check((string) $this->applyPassword, $admin->password)) {
            throw ValidationException::withMessages(['applyPassword' => 'Your password is incorrect.']);
        }
        $this->applyPassword = null;

        $manifest = $this->applyManifest();
        if (! $manifest) {
            Notification::make()->title('No update to apply')->warning()->send();

            return;
        }

        // Server-side re-check — the UI already disables the button on FAIL/unacked
        // WARN, but preflight state (disk space, locks, …) can shift between the
        // preview load and this click, so re-verify rather than trust client state.
        $preview = app(UpdateApplier::class)->preview($manifest);
        $this->applyPreview = $preview->toArray();

        if (! $preview->canProceed()) {
            Notification::make()->title('Update cannot start')->body('Pre-flight checks are failing — see the details below.')->danger()->send();

            return;
        }

        if ($preview->preflight->hasWarnings() && ! $this->previewAcknowledged) {
            Notification::make()->title('Please acknowledge the warnings below before applying')->warning()->send();

            return;
        }

        try {
            $history = app(UpdateApplier::class)->start($manifest, $admin->id);
        } catch (\Throwable $e) {
            Notification::make()->title('Update cannot start')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->applyHistoryId = $history->id;
        $this->applying = true;
        $this->applyPreview = [];
        $this->applyStatus = ['status' => $history->status, 'step' => $history->step];
        Notification::make()->title('Update started')->body('Do not close this window.')->success()->send();
    }

    /** Advance the FSM one step per poll; signal a hard reload on success. */
    public function pollApply(): void
    {
        if (! $this->applying || ! $this->applyHistoryId) {
            return;
        }
        abort_unless($this->canApply(), 403);

        $history = UpdateHistory::find($this->applyHistoryId);
        if (! $history) {
            $this->applying = false;

            return;
        }

        if (! $history->isTerminal()) {
            $history = app(UpdateApplier::class)->advance($history);
        }

        $this->applyStatus = ['status' => $history->status, 'step' => $history->step, 'error' => $history->error];

        if ($history->isTerminal()) {
            $this->applying = false;
            if ($history->isSuccessful()) {
                $this->status = app(UpdateChecker::class)->check(force: true)->toArray();
                Notification::make()->title('Update complete')->body('Now running '.$history->to_version.'.')->success()->send();
                $this->dispatch('update-complete'); // storefront hard-reload
            } else {
                Notification::make()->title('Update did not complete')
                    ->body($history->error ?? 'See the update history.')->danger()->send();
            }
        }
    }

    /** Build the target release manifest from the cached check result. */
    private function applyManifest(): ?array
    {
        $s = $this->status ?? [];
        if (empty($s['update_available']) || empty($s['latest_version'])) {
            return null;
        }

        return [
            'version'         => $s['latest_version'],
            'channel'         => $s['channel'] ?? 'stable',
            'security'        => (bool) ($s['security'] ?? false),
            'download_url'    => $s['download_url'] ?? null,
            'sha256'          => $s['sha256'] ?? null,
            'size_bytes'      => $s['size_bytes'] ?? null,
            'migration_count' => (int) ($s['migration_count'] ?? 0),
            'min_php'         => $s['min_php'] ?? null,
        ];
    }

    public function checkNow(): void
    {
        $status = app(UpdateChecker::class)->check(force: true);
        $this->status = $status->toArray();

        if (! $status->reachable) {
            Notification::make()
                ->title('Could not reach the update server')
                ->body($status->error ?? 'Please try again later.')
                ->warning()
                ->send();

            return;
        }

        if ($status->updateAvailable) {
            Notification::make()
                ->title(($status->security ? 'Security update' : 'Update').' available')
                ->body($status->currentVersion.' → '.$status->latestVersion)
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('You are up to date')
            ->body('Running the latest version ('.$status->currentVersion.').')
            ->success()
            ->send();
    }

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return 24;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-up-circle';
    }

    public static function getNavigationBadge(): ?string
    {
        // Cached only — never triggers a network call on nav render.
        $status = app(UpdateChecker::class)->cached();

        return ($status && $status->updateAvailable) ? '1' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $status = app(UpdateChecker::class)->cached();

        if (! $status || ! $status->updateAvailable) {
            return null;
        }

        return $status->security ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $status = app(UpdateChecker::class)->cached();

        if (! $status || ! $status->updateAvailable) {
            return null;
        }

        return ($status->security ? 'Security update' : 'Update').' available: '.$status->latestVersion;
    }

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('view updates');
    }
}
