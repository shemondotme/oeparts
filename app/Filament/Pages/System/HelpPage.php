<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Help & Documentation';

    protected string $view = 'filament.pages.system.help';

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin->hasRole('super_admin') || $admin->hasPermissionTo('view system information');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-question-mark-circle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Help';
    }

    public static function getNavigationSort(): ?int
    {
        return 99;
    }

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }
}
