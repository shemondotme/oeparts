<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class LogPolicy
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
        return $admin->hasRole('super_admin');
    }

    public function update(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin');
    }

    public function delete(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin');
    }

    protected function getKey(): string
    {
        return $this->permissionKey ?? $this->model ?? 'records';
    }
}
