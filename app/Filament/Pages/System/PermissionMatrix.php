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

    /**
     * @var array<int, array<int, true>>|null role_id => set of permission_ids, lazily built once per request.
     */
    private ?array $roleHasPermissionMap = null;

    /**
     * The Blade view calls hasPermission() up to 3× per matrix cell, for
     * every role × every permission — previously each call did a fresh
     * Role::find() + Spatie's own hasPermissionTo() lookup, so a real
     * matrix (6 roles × ~80 permissions) fired 1000+ separate queries and
     * took ~29s to render, confirmed live via direct page-load timing.
     * One query against the pivot table, memoized for the rest of the
     * request, replaces all of that.
     */
    private function getRoleHasPermissionMap(): array
    {
        if ($this->roleHasPermissionMap === null) {
            $this->roleHasPermissionMap = [];

            foreach (DB::table('role_has_permissions')->get(['role_id', 'permission_id']) as $row) {
                $this->roleHasPermissionMap[$row->role_id][$row->permission_id] = true;
            }
        }

        return $this->roleHasPermissionMap;
    }

    public function hasPermission(int $roleId, int $permissionId): bool
    {
        return isset($this->getRoleHasPermissionMap()[$roleId][$permissionId]);
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

        // The re-render after this action reuses the same component
        // instance's memoized map — without invalidating it, the toggled
        // cell would render its pre-toggle state until the next full load.
        $this->roleHasPermissionMap = null;
    }
}
