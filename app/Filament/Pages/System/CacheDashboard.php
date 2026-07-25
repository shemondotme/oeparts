<?php

namespace App\Filament\Pages\System;

use App\Filament\Widgets\System\CacheHealthRows;
use App\Services\CacheMetricsService;
use App\Services\CacheService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CacheDashboard extends Page
{
    protected static ?string $slug = 'system/cache-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?string $title = 'Cache Dashboard';

    protected string $view = 'filament.pages.system.cache-dashboard';

    protected ?string $subheading = 'Redis cache health, category breakdown, and live key browser. Refreshes every 30 seconds.';

    public string $keyBrowserPattern = '*';

    /** @var array<int, array{key: string, ttl: int, sizeBytes: int|null}> */
    public array $scanResults = [];

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
        return auth('admin')->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->searchKeys();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CacheHealthRows::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('warmAll')
                ->label('Warm All Caches')
                ->icon('heroicon-o-fire')
                ->color('gray')
                ->action(fn () => $this->warmAll()),

            Action::make('exportReport')
                ->label('Export Report')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->exportReport()),

            Action::make('clearApplicationCache')
                ->label('Clear Application Cache')
                ->icon('heroicon-o-trash')
                ->color('danger')
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

        $this->searchKeys();
    }

    public function warmAll(): void
    {
        $service = app(CacheMetricsService::class);
        $adminId = auth('admin')->id();

        $total = 0;
        foreach ($service->warmableCategoryKeys() as $categoryKey) {
            $total += $service->warmCategory($categoryKey, $adminId);
        }

        Notification::make()->title("Warmed {$total} cache entries across all categories")->success()->send();
    }

    public function exportReport(): StreamedResponse
    {
        $service = app(CacheMetricsService::class);
        $health = $service->serverHealth();
        $categories = $service->categoryBreakdown();

        return response()->streamDownload(function () use ($health, $categories) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Cache Dashboard Report', now()->toDateTimeString()]);
            fputcsv($handle, []);

            fputcsv($handle, ['Server Health']);
            fputcsv($handle, ['Metric', 'Value']);
            foreach ($health as $metric => $value) {
                fputcsv($handle, [$metric, is_bool($value) ? ($value ? 'true' : 'false') : $value]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Category Breakdown']);
            fputcsv($handle, ['Category', 'Key Count', 'TTL (minutes)', 'Last Cleared', 'Last Warmed']);
            foreach ($categories as $category) {
                fputcsv($handle, [
                    $category['label'],
                    $category['keyCount'],
                    $category['ttlMinutes'],
                    $category['lastCleared'] ?? 'never',
                    $category['lastWarmed'] ?? 'never',
                ]);
            }

            fclose($handle);
        }, 'cache-dashboard-' . date('Y-m-d-His') . '.csv');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoryBreakdown(): array
    {
        return app(CacheMetricsService::class)->categoryBreakdown();
    }

    public function warmCategory(string $categoryKey): void
    {
        $count = app(CacheMetricsService::class)->warmCategory($categoryKey, auth('admin')->id());

        Notification::make()->title("Warmed {$count} key(s)")->success()->send();
    }

    public function clearCategory(string $categoryKey): void
    {
        $count = app(CacheMetricsService::class)->clearCategory($categoryKey, auth('admin')->id());

        Notification::make()->title("Cleared {$count} key(s)")->success()->send();

        $this->searchKeys();
    }

    public function searchKeys(): void
    {
        $this->scanResults = app(CacheMetricsService::class)->scanKeys($this->keyBrowserPattern);
    }

    public function deleteKey(string $logicalKey): void
    {
        app(CacheMetricsService::class)->deleteKey($logicalKey);

        Notification::make()->title('Key deleted')->success()->send();

        $this->searchKeys();
    }
}
