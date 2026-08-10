<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminPolicy extends BasePolicy
{
    protected string $model = 'admins';

    /**
     * Lockout guards (server-enforced for Delete actions incl. bulk):
     * you cannot delete yourself, and the last active super_admin cannot be
     * deleted — the panel would be orphaned (the Recovery Console restores
     * files/DB, not roles).
     */
    public function delete(Admin $admin, $record): bool
    {
        if ($record instanceof Admin) {
            if ($record->is($admin)) {
                return false;
            }

            if (self::isLastActiveSuperAdmin($record)) {
                return false;
            }
        }

        return parent::delete($admin, $record);
    }

    public static function isLastActiveSuperAdmin(Admin $record): bool
    {
        if (! $record->hasRole('super_admin')) {
            return false;
        }

        return Admin::role('super_admin')
            ->where('is_active', true)
            ->whereKeyNot($record->getKey())
            ->doesntExist();
    }

    /**
     * Server-side guard behind AdminResource's role picker: the picker
     * itself hides super_admin from non-super_admin actors, but that's a UI
     * convenience, not enforcement — without this, any admin holding only
     * "edit admins" could grant super_admin (to themselves or anyone else)
     * via a crafted request, instantly gaining full access through the
     * Gate::before super_admin bypass.
     *
     * @param  array<int, int|string>  $roleIds
     */
    public static function assertCanAssignRoles(array $roleIds): void
    {
        if (auth('admin')->user()?->hasRole('super_admin')) {
            return;
        }

        if (Role::whereIn('id', $roleIds)->where('name', 'super_admin')->exists()) {
            throw ValidationException::withMessages([
                'data.roles' => 'Only a super admin can assign the super_admin role.',
            ]);
        }
    }
}
