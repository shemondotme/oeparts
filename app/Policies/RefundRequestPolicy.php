<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class RefundRequestPolicy extends BasePolicy
{
    protected string $model = 'refund_requests';
    protected ?string $permissionKey = 'refunds';

    // Override update/delete: BasePolicy's default 'edit refunds'/'delete
    // refunds' permission strings are never seeded in RolesSeeder — only
    // 'view refunds' and 'process refunds' exist. 'process refunds' is the
    // resource's sole non-view permission, so it gates editing/deleting too.

    public function update(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('process refunds');
    }

    public function delete(Admin $admin, $record): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('process refunds');
    }

    public function deleteAny(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('process refunds');
    }
}
