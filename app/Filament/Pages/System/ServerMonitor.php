<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Pages\Page;

class ServerMonitor extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Server Monitor';

    protected ?string $subheading = 'CPU, memory, disk, and PHP runtime information.';

    protected string $view = 'filament.pages.system.server-monitor';

    protected static ?string $pollingInterval = '30s';

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasPermissionTo('view system information');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cpu-chip';
    }

    public static function getNavigationLabel(): string
    {
        return 'Server Monitor';
    }

    public static function getNavigationSort(): ?int
    {
        return 46;
    }

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public function getCpuLoad(): array
    {
        $load = sys_getloadavg();
        return [
            '1min' => round($load[0] ?? 0, 2),
            '5min' => round($load[1] ?? 0, 2),
            '15min' => round($load[2] ?? 0, 2),
        ];
    }

    public function getMemoryStats(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = (int) ini_get('memory_limit');
        $limit = $limit > 0 ? $limit : 256 * 1024 * 1024;

        return [
            'usage_bytes' => $usage,
            'peak_bytes' => $peak,
            'limit_bytes' => $limit,
            'usage_mb' => round($usage / 1024 / 1024, 1),
            'peak_mb' => round($peak / 1024 / 1024, 1),
            'limit_mb' => round($limit / 1024 / 1024, 1),
            'usage_percent' => round(($usage / $limit) * 100, 1),
        ];
    }

    public function getDiskStats(): array
    {
        $path = storage_path();
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;

        return [
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'usage_percent' => round(($used / $total) * 100, 1),
        ];
    }

    public function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => get_loaded_extensions(),
        ];
    }

    public function getLaravelInfo(): array
    {
        return [
            'version' => app()->version(),
            'environment' => config('app.env'),
            'debug' => config('app.debug') ? 'Yes' : 'No',
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
            'database' => config('database.default'),
        ];
    }
}
