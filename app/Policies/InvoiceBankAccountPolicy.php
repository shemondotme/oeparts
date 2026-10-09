<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;

/**
 * Where invoice money is sent is the classic invoice-fraud target (swap the IBAN,
 * collect the payment), so every action, even looking, needs the dedicated
 * 'manage bank accounts' permission rather than the broader invoice ones.
 */
class InvoiceBankAccountPolicy extends BasePolicy
{
    protected string $model = 'bank accounts';

    private function allowed(Admin $admin): bool
    {
        return $admin->hasRole('super_admin') || $admin->can('manage bank accounts');
    }

    public function viewAny(Admin $admin): bool
    {
        return $this->allowed($admin);
    }

    public function view(Admin $admin, $record): bool
    {
        return $this->allowed($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->allowed($admin);
    }

    public function update(Admin $admin, $record): bool
    {
        return $this->allowed($admin);
    }

    public function delete(Admin $admin, $record): bool
    {
        return $this->allowed($admin);
    }

    public function deleteAny(Admin $admin): bool
    {
        return $this->allowed($admin);
    }
}
