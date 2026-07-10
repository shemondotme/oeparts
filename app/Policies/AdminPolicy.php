<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

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
}
