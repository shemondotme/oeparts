<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Reports extends Cluster
{
    protected static ?string $slug = 'reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Reports';

    protected static ?int $navigationSort = 80;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }
}
