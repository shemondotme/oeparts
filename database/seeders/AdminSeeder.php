<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * One admin per role, used for testing. Kept idempotent via updateOrCreate
     * (keyed by email) so re-seeding preserves these exact credentials and roles
     * instead of creating duplicates. Requires RolesSeeder to have run first.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Super Admin',   'email' => 'superadmin@oeparts.test', 'password' => 'superadmin@oeparts', 'role' => 'super_admin'],
            ['name' => 'Admin',         'email' => 'admin@oeparts.test',      'password' => 'admin@oeparts',      'role' => 'admin'],
            ['name' => 'Manager',       'email' => 'manager@oeparts.test',    'password' => 'manager@oeparts',    'role' => 'manager'],
            ['name' => 'Catalog Admin', 'email' => 'catalog@oeparts.test',    'password' => 'catalog@oeparts',    'role' => 'catalog_admin'],
            ['name' => 'Support',       'email' => 'support@oeparts.test',    'password' => 'support@oeparts',    'role' => 'support'],
        ];

        foreach ($accounts as $account) {
            $admin = Admin::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'is_active' => true,
                ],
            );

            $admin->syncRoles([$account['role']]);
        }
    }
}
