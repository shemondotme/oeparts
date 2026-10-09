<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/**
 * Saved invoice clients follow the custom-invoice permissions (view / create / edit).
 * A client that invoices were written to is never deleted from the UI.
 */
class InvoiceClientPolicy extends BasePolicy
{
    protected string $model = 'custom invoices';

    public function delete(Admin $admin, $record): bool
    {
        return $record->invoices()->doesntExist() && parent::delete($admin, $record);
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
