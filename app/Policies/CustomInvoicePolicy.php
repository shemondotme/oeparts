<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

class CustomInvoicePolicy extends BasePolicy
{
    protected string $model = 'custom invoices';

    // Numbered invoices are accounting records: a mistaken one is cancelled
    // (keeps its number), never deleted.
    public function delete(Admin $admin, $record): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }

    public function restore(Admin $admin, $record): bool
    {
        return false;
    }

    public function restoreAny(Admin $admin): bool
    {
        return false;
    }

    public function forceDelete(Admin $admin, $record): bool
    {
        return false;
    }

    public function forceDeleteAny(Admin $admin): bool
    {
        return false;
    }
}
