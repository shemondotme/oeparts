<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionMatrix extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Permission Matrix';

    protected string $view = 'filament.pages.system.permission-matrix';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public $matrix = [];

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-key';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getRoles(): \Illuminate\Support\Collection
    {
        return Role::where('guard_name', 'admin')->get();
    }

    public function getPermissionsGrouped(): array
    {
        $permissions = Permission::where('guard_name', 'admin')->get();

        $grouped = [];
        foreach ($permissions as $permission) {
            $parts = explode(' ', $permission->name);
            $action = array_shift($parts);
            $module = implode(' ', $parts) ?? 'general';

            $module = ucfirst($module);
            $action = ucfirst($action);

            $grouped[$module][$permission->id] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'action' => $action,
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    public function hasPermission(int $roleId, int $permissionId): bool
    {
        $role = Role::find($roleId);

        if (! $role) {
            return false;
        }

        return $role->hasPermissionTo($permissionId);
    }

    public function togglePermission(int $roleId, int $permissionId): void
    {
        $role = Role::find($roleId);
        $permission = Permission::find($permissionId);

        if (! $role || ! $permission) {
            return;
        }

        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);

            Notification::make()
                ->title("Permission revoked")
                ->body("Removed '{$permission->name}' from {$role->name}")
                ->info()
                ->send();
        } else {
            $role->givePermissionTo($permission);

            Notification::make()
                ->title("Permission granted")
                ->body("Added '{$permission->name}' to {$role->name}")
                ->success()
                ->send();
        }
    }
}
