<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Settings extends Cluster
{
    protected static ?string $slug = 'settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $clusterBreadcrumb = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.clusters.settings';

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasAnyRole(['super_admin', 'admin']);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public function mount(): void
    {
    }
}
