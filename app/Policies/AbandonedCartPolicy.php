<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class AbandonedCartPolicy extends BasePolicy
{
    protected string $model = 'abandoned_carts';
    // Seeder spells this permission with a space, not the model's
    // underscore — override the key so viewAny()/view() resolve correctly.
    protected ?string $permissionKey = 'abandoned carts';

    // This resource has never had a second, more-privileged permission
    // tier beyond 'view abandoned carts' — update/delete gate on the same
    // permission rather than the never-seeded 'edit abandoned_carts'.

    public function update(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view abandoned carts');
    }

    public function delete(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view abandoned carts');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('view abandoned carts');
    }
}
