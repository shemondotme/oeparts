<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class Reports extends Cluster
{
    protected static ?string $slug = 'reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $clusterBreadcrumb = 'Reports';

    protected static ?string $title = 'Reports';

    protected static ?int $navigationSort = 80;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected string $view = 'filament.clusters.reports';

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasAnyRole(['super_admin', 'admin']);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public function mount(): void
    {
    }
}
