<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected string $model;

    /**
     * Override this to map to the correct Spatie permission key.
     * If null, falls back to $this->model (e.g., 'orders', 'products').
     */
    protected ?string $permissionKey = null;

    public function viewAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view ' . $this->getKey());
    }

    public function view(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view ' . $this->getKey());
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('create ' . $this->getKey());
    }

    public function update(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('edit ' . $this->getKey());
    }

    public function delete(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('delete ' . $this->getKey());
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('delete ' . $this->getKey());
    }

    protected function getKey(): string
    {
        return $this->permissionKey ?? $this->model ?? 'records';
    }
}
