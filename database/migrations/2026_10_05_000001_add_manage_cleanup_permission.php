<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin Cleanup dashboard permission (part of the Update & Recovery System
 * family, Module 21) — follows create_update_system_permissions.php's own
 * pattern exactly: creates the permission for EXISTING installs (fresh
 * installs get it via RolesSeeder, already updated). guard_name = 'admin'.
 * NOT assigned to any role — super_admin only, via Gate::before() (LOCKED
 * DECISION #1), same as every other permission in this family. Idempotent +
 * reversible (CLAUDE.md rule #42).
 */
return new class extends Migration
{
    private string $permission = 'manage cleanup';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::updateOrCreate(
            ['name' => $this->permission, 'guard_name' => 'admin'],
            ['name' => $this->permission, 'guard_name' => 'admin'],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'admin')
            ->where('name', $this->permission)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
