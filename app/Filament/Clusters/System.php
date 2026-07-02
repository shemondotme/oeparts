<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class System extends Cluster
{
    use Concerns\RedirectsNavigationToFirstChild;

    protected static ?string $slug = 'system';

    protected static ?string $navigationLabel = 'System';

    protected static ?string $clusterBreadcrumb = 'System';

    protected static ?string $title = 'System';

    protected static ?int $navigationSort = 90;

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
        $user = auth('admin')->user();

        return $user->hasRole('super_admin') || $user->hasPermissionTo('view system information');
    }
}
