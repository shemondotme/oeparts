<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Update & Recovery System (Module 21) permissions.
 *
 * Creates the permissions for existing installs (fresh installs get them via
 * RolesSeeder). guard_name = 'admin' (Spatie is on the Admin model). These are
 * NOT assigned to any role — they belong to super_admin only, which bypasses
 * the gate via Gate::before() (LOCKED DECISION #1). Idempotent + reversible
 * (CLAUDE.md rule #42).
 */
return new class extends Migration
{
    private array $permissions = [
        'view updates',
        'apply updates',
        'manage backups',
        'restore backups',
        'run recovery',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'admin'],
                ['name' => $name, 'guard_name' => 'admin'],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', $this->permissions)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
