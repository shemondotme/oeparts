<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Filament\Widgets\System\QueueStats;
use Filament\Actions\Action;
use Filament\Pages\Page;

class QueueMonitor extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Queue Monitor';

    protected ?string $subheading = 'Real-time queue depth, throughput, and failure rates. Refreshes every 30 seconds.';

    protected string $view = 'filament.pages.system.queue-monitor';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin->hasRole('super_admin') || $admin->hasPermissionTo('view system information');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-server-stack';
    }

    public static function getNavigationLabel(): string
    {
        return 'Queue Monitor';
    }

    public static function getNavigationSort(): ?int
    {
        return 48;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            QueueStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('failedJobs')
                ->label('Failed Jobs')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->url(fn () => FailedJobsPage::getUrl()),
        ];
    }
}
