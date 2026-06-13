<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class Content extends Cluster
{
    protected static ?string $slug = 'content';

    protected static ?string $navigationLabel = 'Content';

    protected static ?string $clusterBreadcrumb = 'Content';

    protected static ?string $title = 'Content';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.clusters.content';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasAnyRole(['super_admin', 'admin', 'catalog_admin']);
    }

    public function mount(): void
    {
    }
}
