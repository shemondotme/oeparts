<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view roles');
    }

    public function view(Admin $admin, Role $role): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view roles');
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('create roles');
    }

    public function update(Admin $admin, Role $role): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('edit roles');
    }

    public function delete(Admin $admin, Role $role): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('delete roles');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('delete roles');
    }

    public function restore(Admin $admin, Role $role): bool
    {
        return false;
    }

    public function forceDelete(Admin $admin, Role $role): bool
    {
        return false;
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return false;
    }

    public function restoreAny(Admin $admin): bool
    {
        return false;
    }

    public function replicate(Admin $admin, Role $role): bool
    {
        return false;
    }

    public function reorder(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('edit roles');
    }
}
