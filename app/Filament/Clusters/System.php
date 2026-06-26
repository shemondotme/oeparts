<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class System extends Cluster
{
    protected static ?string $slug = 'system';

    protected static ?string $navigationLabel = 'System';

    protected static ?string $clusterBreadcrumb = 'System';

    protected static ?string $title = 'System';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.clusters.system';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-server-stack';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function mount(): void
    {
    }
}
