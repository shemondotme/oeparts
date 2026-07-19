<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Filament\Widgets\System\CacheStats;
use App\Services\CacheService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CacheDashboard extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Cache Dashboard';

    protected string $view = 'filament.pages.system.cache-dashboard';

    protected ?string $subheading = 'Redis cache health, hit rate, and memory usage. Refreshes every 30 seconds.';

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-server-stack';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CacheStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearApplicationCache')
                ->label('Clear Application Cache')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This flushes all application cache keys from Redis. Continue?')
                ->action(fn () => $this->clearApplicationCache()),
        ];
    }

    public function clearApplicationCache(): void
    {
        $count = app(CacheService::class)->purgeAllApplicationCacheKeys();

        if ($count === -1) {
            Notification::make()
                ->title('Redis not available')
                ->body('Cannot clear cache: Redis driver is not active.')
                ->danger()
                ->send();

            return;
        }

        if ($count === 0) {
            Notification::make()->title('No application cache keys found')->info()->send();

            return;
        }

        Notification::make()->title("Cleared {$count} cache keys")->success()->send();
    }
}
