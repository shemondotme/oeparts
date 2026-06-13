<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Models\ActivityLog;
use App\Services\HealthCheckService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class HealthCheckDashboard extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Health Check';

    protected string $view = 'filament.pages.system.health-check';

    protected static ?string $pollingInterval = '30s';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public array $checkHistory = [];

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
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getHealthResults(): array
    {
        return app(HealthCheckService::class)->runAll();
    }

    public function getOverallStatusColor(): string
    {
        $results = $this->getHealthResults();

        return match ($results['status']) {
            'ok' => 'success',
            'degraded' => 'warning',
            default => 'danger',
        };
    }

    public function getOverallStatusIcon(): string
    {
        $results = $this->getHealthResults();

        return match ($results['status']) {
            'ok' => 'heroicon-o-check-badge',
            'degraded' => 'heroicon-o-exclamation-triangle',
            default => 'heroicon-o-x-circle',
        };
    }

    public function getStatusForCheck(string $status): array
    {
        return match ($status) {
            'ok' => ['color' => 'success', 'icon' => 'heroicon-o-check-circle', 'pulse' => true],
            'fail' => ['color' => 'danger', 'icon' => 'heroicon-o-x-circle', 'pulse' => false],
            'missing' => ['color' => 'warning', 'icon' => 'heroicon-o-question-mark-circle', 'pulse' => false],
            'stale' => ['color' => 'warning', 'icon' => 'heroicon-o-clock', 'pulse' => false],
            'unknown' => ['color' => 'gray', 'icon' => 'heroicon-o-minus-circle', 'pulse' => false],
            default => ['color' => 'gray', 'icon' => 'heroicon-o-minus-circle', 'pulse' => false],
        };
    }

    public function pollingInterval(): ?string
    {
        return '30s';
    }

    public function runCheckAction(): void
    {
        $results = $this->getHealthResults();

        $this->checkHistory[] = [
            'time' => now()->format('H:i:s'),
            'status' => $results['status'],
            'checks' => $results['checks'],
        ];

        if (count($this->checkHistory) > 12) {
            $this->checkHistory = array_slice($this->checkHistory, -12);
        }

        $this->logAction('health_check_run', 'Health check executed: ' . $results['status']);

        Notification::make()
            ->title($results['status'] === 'ok' ? 'All systems healthy' : 'Some checks failed')
            ->{$results['status'] === 'ok' ? 'success' : 'warning'}()
            ->send();
    }

    public function clearCacheRemediation(): void
    {
        try {
            Artisan::call('cache:clear');
            $this->logAction('remediation_action', 'Cache cleared via health check remediation');
            Notification::make()
                ->title('Cache cleared')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to clear cache')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetSchedulerHeartbeat(): void
    {
        try {
            Cache::put('scheduler_heartbeat', now(), 360);
            $this->logAction('remediation_action', 'Scheduler heartbeat reset via health check remediation');
            Notification::make()
                ->title('Scheduler heartbeat reset')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to reset scheduler')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function remediateDatabase(): void
    {
        $this->logAction('remediation_action', 'Database remediation requested — manual check required');
        Notification::make()
            ->title('Database connection failed')
            ->body('Check your database credentials in .env and ensure the MySQL server is running.')
            ->warning()
            ->send();
    }

    public function getRemediationForCheck(string $checkKey): ?array
    {
        return match ($checkKey) {
            'database' => [
                'label' => 'Check Config',
                'action' => 'remediateDatabase',
                'icon' => 'heroicon-o-wrench',
                'color' => 'danger',
                'needsConfirmation' => false,
            ],
            'cache' => [
                'label' => 'Clear Cache',
                'action' => 'clearCacheRemediation',
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'warning',
                'needsConfirmation' => true,
                'confirmMessage' => 'This will flush all cached data. Continue?',
            ],
            'queue' => [
                'label' => 'Check Config',
                'action' => null,
                'icon' => 'heroicon-o-arrow-top-right-on-square',
                'color' => 'warning',
                'needsConfirmation' => false,
                'externalUrl' => \App\Filament\Pages\System\SetupAssistant::getUrl(),
            ],
            'storage' => [
                'label' => 'Check Permissions',
                'action' => null,
                'icon' => 'heroicon-o-arrow-top-right-on-square',
                'color' => 'warning',
                'needsConfirmation' => false,
                'externalUrl' => \App\Filament\Pages\System\SetupAssistant::getUrl(),
            ],
            'scheduler' => [
                'label' => 'Reset Heartbeat',
                'action' => 'resetSchedulerHeartbeat',
                'icon' => 'heroicono-arrow-path',
                'color' => 'warning',
                'needsConfirmation' => true,
                'confirmMessage' => 'Reset the scheduler heartbeat timestamp. This will not start the scheduler if it is not running.',
            ],
            'assets' => [
                'label' => 'View Setup',
                'action' => null,
                'icon' => 'heroicon-o-arrow-top-right-on-square',
                'color' => 'warning',
                'needsConfirmation' => false,
                'externalUrl' => \App\Filament\Pages\System\SetupAssistant::getUrl(),
            ],
            default => null,
        };
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
