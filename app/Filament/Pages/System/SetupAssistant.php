<?php

namespace App\Filament\Pages\System;

use App\Console\Commands\DemoSetupCommand;
use App\Filament\Clusters\System as SystemCluster;
use App\Models\ActivityLog;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class SetupAssistant extends Page
{
    protected static ?string $cluster = SystemCluster::class;

    protected static ?string $title = 'Setup Assistant';

    protected string $view = 'filament.pages.system.setup-assistant';

    // Listed in the System cluster nav — this is the operator's onboarding
    // surface (setup checklist, demo data, maintenance) and used to be
    // reachable only through a single Health Check link.
    public static function getNavigationSort(): ?int
    {
        return 69;
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getSetupSteps(): array
    {
        return [
            [
                'key' => 'installed',
                'label' => 'Installation Complete',
                'description' => 'Lock file present in storage/',
                'done' => $this->isInstalled(),
            ],
            [
                'key' => 'php',
                'label' => 'PHP 8.2+',
                'description' => 'Required PHP version',
                'done' => version_compare(phpversion(), '8.2', '>='),
            ],
            [
                'key' => 'database',
                'label' => 'Database Connected',
                'description' => 'MySQL connection active',
                'done' => $this->getDbStatus()['ok'],
            ],
            [
                'key' => 'cache',
                'label' => 'Cache Driver',
                'description' => 'Redis or file cache configured',
                'done' => in_array(config('cache.default'), ['redis', 'file']),
            ],
            [
                'key' => 'redis',
                'label' => 'Redis Connected',
                'description' => 'Required for sessions & queues in production',
                'done' => $this->getRedisStatus()['ok'],
            ],
            [
                'key' => 'queue',
                'label' => 'Queue Driver',
                'description' => 'Queue connection configured',
                'done' => config('queue.default') !== 'sync',
            ],
            [
                'key' => 'assets',
                'label' => 'Compiled Assets',
                'description' => 'Vite build manifest present',
                'done' => file_exists(public_path('build/manifest.json')),
            ],
            [
                'key' => 'migrations',
                'label' => 'Migrations Current',
                'description' => 'All migrations have run',
                'done' => str_contains($this->getMigrationStatus(), 'migrations run'),
            ],
        ];
    }

    public function getSetupProgress(): int
    {
        $steps = $this->getSetupSteps();
        $done = collect($steps)->where('done', true)->count();

        return (int) round(($done / count($steps)) * 100);
    }

    public function getProgressColor(): string
    {
        $pct = $this->getSetupProgress();

        return match (true) {
            $pct >= 80 => 'success',
            $pct >= 50 => 'warning',
            default => 'danger',
        };
    }

    public function isInstalled(): bool
    {
        return File::exists(storage_path('installed.lock'));
    }

    public function getInstalledAt(): ?string
    {
        if (! $this->isInstalled()) {
            return null;
        }

        return File::get(storage_path('installed.lock'));
    }

    public function getPhpVersion(): string
    {
        return phpversion();
    }

    public function getDbStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true, 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function getDbSize(): string
    {
        try {
            $db = DB::connection()->getDatabaseName();
            $size = DB::select('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = ?', [$db]);

            return ($size[0]->size ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    public function getCacheStatus(): string
    {
        try {
            Cache::store(config('cache.default'))->has('health_check');

            return config('cache.default');
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function getRedisStatus(): array
    {
        if (config('cache.default') !== 'redis') {
            return ['ok' => true, 'message' => 'Not in use'];
        }

        try {
            Redis::connection()->ping();

            return ['ok' => true, 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function getQueueStatus(): string
    {
        return config('queue.default');
    }

    public function getScheduleLastRun(): string
    {
        $logFile = storage_path('logs/schedule.log');
        if (! File::exists($logFile)) {
            return 'Never';
        }

        return date('Y-m-d H:i', File::lastModified($logFile));
    }

    public function getMigrationStatus(): string
    {
        if (! Schema::hasTable('migrations')) {
            return 'Not run';
        }

        try {
            $count = DB::table('migrations')->count();
            $last = DB::table('migrations')->orderByDesc('id')->first();

            return "{$count} migrations run (last: {$last->migration})";
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function getFailedJobsCount(): int
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return 0;
            }

            return DB::table('failed_jobs')->count();
        } catch (\Exception) {
            return 0;
        }
    }

    public function getActionRisk(string $action): array
    {
        return match ($action) {
            'seedDemoData' => [
                'level' => 'HIGH',
                'color' => 'danger',
                'label' => 'Destructive — Populates database with sample data. May overwrite existing demo records. Cannot be undone.',
                'requireTypedConfirmation' => true,
                'confirmText' => 'SEED',
            ],
            'runMigrations' => [
                'level' => 'MEDIUM',
                'color' => 'warning',
                'label' => 'Applies pending database schema changes. May cause temporary downtime during migration. Ensure backup is taken first.',
                'requireTypedConfirmation' => false,
                'confirmText' => null,
            ],
            'toggleMaintenance' => [
                'level' => 'MEDIUM',
                'color' => 'warning',
                'label' => $this->isDownForMaintenance()
                    ? 'Brings the storefront back online for all visitors.'
                    : 'Shows visitors the maintenance page (503). The admin panel stays fully accessible; whitelisted IPs from Maintenance Settings can still browse.',
                'requireTypedConfirmation' => false,
                'confirmText' => null,
            ],
            'clearCache' => [
                'level' => 'LOW',
                'color' => 'success',
                'label' => 'Rebuilds the framework caches (config, routes, views, events). Application data, sessions, and the settings cache are not touched.',
                'requireTypedConfirmation' => false,
                'confirmText' => null,
            ],
            'clearViews' => [
                'level' => 'LOW',
                'color' => 'success',
                'label' => 'Clears compiled Blade templates. They will be recompiled on next page load. No data loss.',
                'requireTypedConfirmation' => false,
                'confirmText' => null,
            ],
            default => [
                'level' => 'LOW',
                'color' => 'success',
                'label' => 'Performs a standard maintenance operation.',
                'requireTypedConfirmation' => false,
                'confirmText' => null,
            ],
        };
    }

    public function clearCache(): void
    {
        // Framework caches only. NEVER cache:clear here — that is
        // Cache::flush() on the shared store (rule #5): it destroys
        // sessions on production Redis and every cached settings group.
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('event:clear');

        $this->logAction('clear_cache', 'Framework caches cleared via admin panel');

        Notification::make()
            ->title('System caches cleared')
            ->body('Config, route, view, and event caches were rebuilt.')
            ->success()
            ->send();
    }

    public function clearViews(): void
    {
        Artisan::call('view:clear');

        $this->logAction('clear_views', 'Compiled views cleared via admin panel');

        Notification::make()
            ->title('Views cleared')
            ->success()
            ->send();
    }

    public function runMigrations(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);

            $this->logAction('run_migrations', 'Database migrations executed');

            Notification::make()
                ->title('Migrations complete')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Migration failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function seedDemoData(): void
    {
        try {
            Artisan::call('demo:setup', ['--seed' => true, '--yes' => true]);

            $this->logAction('seed_demo_data', 'Demo data seeded');

            Notification::make()
                ->title('Demo data seeded')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Demo setup failed', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Demo setup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function toggleMaintenance(): void
    {
        // Drives the settings-based MaintenanceMode middleware (Module 19)
        // — the same flag as Settings > Maintenance. Never `artisan down`:
        // that is a second, disconnected maintenance system whose 503 also
        // locks the operator out of this very panel.
        $wasDown = $this->isDownForMaintenance();

        app(\App\Services\SettingsService::class)->set('maintenance.enabled', $wasDown ? '0' : '1');

        if ($wasDown) {
            $this->logAction('maintenance_disabled', 'Maintenance mode disabled, storefront back online');
            Notification::make()
                ->title('Maintenance mode disabled')
                ->success()
                ->send();
        } else {
            $this->logAction('maintenance_enabled', 'Maintenance mode enabled, storefront offline');
            Notification::make()
                ->title('Maintenance mode enabled')
                ->body('Visitors see the maintenance page; the admin panel stays reachable.')
                ->warning()
                ->send();
        }
    }

    public function isDownForMaintenance(): bool
    {
        return (bool) settings('maintenance.enabled', false);
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_cache')
                ->label('Clear System Caches')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->modalHeading('Clear System Caches')
                ->modalDescription($this->getActionRisk('clearCache')['label'])
                ->modalIcon('heroicon-o-arrow-path')
                ->modalSubmitActionLabel('Yes, Clear Caches')
                ->action('clearCache'),

            Action::make('clear_views')
                ->label('Clear Views')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->modalHeading('Clear Compiled Views')
                ->modalDescription($this->getActionRisk('clearViews')['label'])
                ->modalIcon('heroicon-o-eye-slash')
                ->modalSubmitActionLabel('Yes, Clear Views')
                ->action('clearViews'),

            Action::make('toggle_maintenance')
                ->label(fn (): string => $this->isDownForMaintenance() ? 'Disable Maintenance' : 'Enable Maintenance')
                ->icon('heroicon-o-wrench')
                ->color(fn (): string => $this->isDownForMaintenance() ? 'success' : 'warning')
                ->modalHeading(fn (): string => $this->isDownForMaintenance() ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode')
                ->modalDescription(fn () => $this->getActionRisk('toggleMaintenance')['label'])
                ->modalIcon('heroicon-o-wrench')
                ->modalSubmitActionLabel(fn (): string => $this->isDownForMaintenance() ? 'Yes, Bring Site Online' : 'Yes, Enable Maintenance')
                ->action('toggleMaintenance'),
        ];
    }
}
