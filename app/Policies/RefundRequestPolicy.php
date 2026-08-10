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

    // No RefundRequestResource create route exists (see
    // ListRefundRequests::getHeaderActions(), which deliberately omits
    // CreateAction — refund requests only ever originate from customers via
    // the storefront). Left un-overridden, this fell through to BasePolicy's
    // default 'create refunds' permission, which — unlike 'process refunds'
    // above — is never seeded for any role, so it was silently super_admin-
    // only already via Gate::before. Made that explicit here instead of
    // leaving it as a side effect of an unseeded permission string, matching
    // update()/delete()'s override below.
    public function create(Admin $admin): bool
    {
        return $admin->hasRole('super_admin');
    }

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
