<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Filament\Widgets\System\HealthCheckStats;
use App\Models\ActivityLog;
use App\Services\CacheService;
use App\Services\HealthCheckService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class HealthCheckDashboard extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Health Check';

    protected string $view = 'filament.pages.system.health-check';

    protected ?string $subheading = 'Live status of core services. Stats refresh automatically every 30 seconds.';

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-heart';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasRole('super_admin') ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            HealthCheckStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runChecks')
                ->label('Run Checks')
                ->icon('heroicon-o-play')
                ->action(fn () => $this->runCheck()),

            ActionGroup::make([
                Action::make('clearCache')
                    ->label('Clear Cache')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This clears all application cache keys (not sessions). Continue?')
                    ->action(fn () => $this->clearCacheRemediation()),

                Action::make('resetScheduler')
                    ->label('Reset Scheduler Heartbeat')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Reset the scheduler heartbeat timestamp. This will not start the scheduler if it is not running.')
                    ->action(fn () => $this->resetSchedulerHeartbeat()),

                Action::make('setup')
                    ->label('Open Setup Assistant')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => SetupAssistant::getUrl()),
            ])
                ->label('Remediation')
                ->icon('heroicon-o-wrench-screwdriver')
                ->button()
                ->color('gray'),
        ];
    }

    public function runCheck(): void
    {
        $results = app(HealthCheckService::class)->runAll();

        $this->logAction('health_check_run', 'Health check executed: ' . $results['status']);

        Notification::make()
            ->title($results['status'] === 'ok' ? 'All systems healthy' : 'Some checks need attention')
            ->{$results['status'] === 'ok' ? 'success' : 'warning'}()
            ->send();
    }

    public function clearCacheRemediation(): void
    {
        try {
            // Targeted key-scoped purge, NOT Artisan cache:clear — a full flush
            // nukes the whole cache store, sessions included if they share the
            // same Redis connection (CLAUDE.md rule #5: never Cache::flush()).
            $count = app(CacheService::class)->purgeAllApplicationCacheKeys();

            if ($count === -1) {
                Notification::make()->title('Redis not available')->body('Cannot clear cache: Redis driver is not active.')->danger()->send();

                return;
            }

            $this->logAction('remediation_action', "Cache cleared via health check remediation ({$count} keys)");
            Notification::make()->title("Cache cleared ({$count} keys)")->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Failed to clear cache')->body($e->getMessage())->danger()->send();
        }
    }

    public function resetSchedulerHeartbeat(): void
    {
        try {
            Cache::put('scheduler_heartbeat', now(), 360);
            $this->logAction('remediation_action', 'Scheduler heartbeat reset via health check remediation');
            Notification::make()->title('Scheduler heartbeat reset')->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title('Failed to reset scheduler')->body($e->getMessage())->danger()->send();
        }
    }

    protected function logAction(string $action, string $description): void
    {
        ActivityLog::create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'model_type' => self::class,
            'model_id' => null,
            'old_values' => [],
            'new_values' => ['description' => $description],
            'ip_address' => request()->ip(),
        ]);
    }
}
