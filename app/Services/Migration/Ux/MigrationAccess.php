<?php

namespace App\Services\Migration\Ux;

/**
 * Who may do what on the Migration pages, in one place so a panel, a page and a test can never disagree. Both permissions
 * belong to super_admin only (Gate::before), like the rest of the update and recovery family.
 */
final class MigrationAccess
{
    public static function canExport(): bool
    {
        return (bool) auth('admin')->user()?->can('export migration');
    }

    public static function canImport(): bool
    {
        return (bool) auth('admin')->user()?->can('import migration');
    }

    public static function canOpen(): bool
    {
        return self::canExport() || self::canImport();
    }
}
