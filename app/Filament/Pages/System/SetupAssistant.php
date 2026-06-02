<?php

namespace App\Filament\Pages\System;

use App\Console\Commands\DemoSetupCommand;
use Filament\Actions\Action;
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
    protected static ?string $title = 'Setup Assistant';

    protected string $view = 'filament.pages.system.setup-assistant';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 69;
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
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
        $content = File::get(storage_path('installed.lock'));

        return $content;
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
            $size = DB::select("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = ?", [$db]);

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
        $connection = config('queue.default');

        return $connection;
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

    public function clearCache(): void
    {
        Artisan::call('optimize:clear');
        Notification::make()
            ->title('Cache cleared')
            ->body('Application cache, views, routes, and config have been cleared.')
            ->success()
            ->send();
    }

    public function clearViews(): void
    {
        Artisan::call('view:clear');
        Notification::make()
            ->title('Views cleared')
            ->body('Compiled Blade templates have been cleared.')
            ->success()
            ->send();
    }

    public function runMigrations(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            Notification::make()
                ->title('Migrations complete')
                ->body($output)
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
            $output = Artisan::output();

            Notification::make()
                ->title('Demo data seeded')
                ->body('Demo setup completed successfully.')
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
        if (File::exists(storage_path('framework/down'))) {
            Artisan::call('up');

            Notification::make()
                ->title('Maintenance mode disabled')
                ->body('The site is now accessible to all users.')
                ->success()
                ->send();
        } else {
            Artisan::call('down', ['--render' => 'errors.503']);

            Notification::make()
                ->title('Maintenance mode enabled')
                ->body('The site is now in maintenance mode. Only admins can access the panel.')
                ->warning()
                ->send();
        }
    }

    public function isDownForMaintenance(): bool
    {
        return File::exists(storage_path('framework/down'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_cache')
                ->label('Clear Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('clearCache'),
            Action::make('clear_views')
                ->label('Clear Views')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->action('clearViews'),
            Action::make('toggle_maintenance')
                ->label(fn (): string => $this->isDownForMaintenance() ? 'Disable Maintenance' : 'Enable Maintenance')
                ->icon('heroicon-o-wrench')
                ->color(fn (): string => $this->isDownForMaintenance() ? 'success' : 'warning')
                ->action('toggleMaintenance'),
        ];
    }
}
