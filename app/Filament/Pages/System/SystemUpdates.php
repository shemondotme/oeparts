<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Services\Updates\UpdateChecker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Update & Recovery System (Module 21, Chunk 1.3) — the "System Updates" page.
 *
 * Level 1 (notification): shows the installed vs latest version, the changelog,
 * and highlights security releases. Applying updates (Level 2) arrives in Phase 3;
 * for now the page links to the release notes. Lazy-tier check runs on mount()
 * (cache-backed); "Check now" forces a fresh check.
 */
class SystemUpdates extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'System Updates';

    protected string $view = 'filament.pages.system.system-updates';

    /** @var array<string,mixed>|null Result of UpdateChecker::check()->toArray() */
    public ?array $status = null;

    public function mount(): void
    {
        // Lazy tier — served from cache unless the TTL has expired.
        $this->status = app(UpdateChecker::class)->check()->toArray();
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
