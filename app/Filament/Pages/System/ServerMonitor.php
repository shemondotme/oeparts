<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Filament\Widgets\System\ServerStats;
use Filament\Pages\Page;

class ServerMonitor extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Server Monitor';

    protected ?string $subheading = 'CPU, memory, disk, and PHP/Laravel runtime information. Refreshes every 30 seconds.';

    protected string $view = 'filament.pages.system.server-monitor';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin->hasRole('super_admin') || $admin->hasPermissionTo('view system information');
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

    protected function getHeaderWidgets(): array
    {
        return [
            ServerStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
