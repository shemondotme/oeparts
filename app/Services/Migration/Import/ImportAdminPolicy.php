<?php

namespace App\Services\Migration\Import;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Who can sign in to the site once the import is over. The administrators of the package win: they replaced the table. The
 * safety net is that at least one of them must be able to sign in (active, a super administrator, a real password hash), and
 * if the person gave an "extra administrator" (the break-glass account; it exists for a copy nobody has the old passwords for)
 * that one is created or updated by e-mail address, exactly as the installer creates its first administrator.
 *
 * The extra administrator's password arrives as a hash, never as text, and the hash is removed from the run as soon as it was used.
 */
final class ImportAdminPolicy
{
    /** @return int how many administrators can sign in right now */
    public function usable(): int
    {
        return (int) DB::table('admins')->where('is_active', 1)->where('role', 'super_admin')->where('password', 'like', '$2y$%')->count();
    }

    /**
     * Create or update the extra administrator, if one was asked for. Repeating it changes nothing.
     *
     * @param  array<string, mixed>  $options  the run's options (the hash is taken out of them)
     * @return string|null the e-mail address of the account that was written, or null when none was asked for
     */
    public function ensureExtra(array &$options): ?string
    {
        $extra = (array) ($options['extra_admin'] ?? []);

        if ($extra === [] || ! is_string($extra['email'] ?? null) || ! is_string($extra['password_hash'] ?? null) || ! str_starts_with($extra['password_hash'], '$2y$')) {
            return null;
        }

        $admin = Admin::updateOrCreate(
            ['email' => $extra['email']],
            ['name' => (string) ($extra['name'] ?? 'Administrator'), 'password' => $extra['password_hash'], 'is_active' => true, 'role' => 'super_admin', 'email_verified_at' => now()],
        );
        Role::findOrCreate('super_admin', 'admin'); // an imported site has its roles already; a bare one gets the one it needs
        $admin->syncRoles(['super_admin']);

        unset($options['extra_admin']['password_hash']);

        return $extra['email'];
    }
}
