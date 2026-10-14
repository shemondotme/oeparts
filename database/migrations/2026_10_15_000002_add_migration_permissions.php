<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Site migration permissions on the admin guard — follows add_manage_cleanup_permission.php exactly: creates them for EXISTING
 * installs (fresh installs get them via RolesSeeder). Not assigned to any role: super_admin only, via Gate::before(), like every
 * other permission of the update and recovery family. Idempotent and reversible.
 *
 *   export migration  make packages, share them, download them
 *   import migration  bring a package in from a file, a link or the command line and replace this site with it
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['export migration', 'import migration'];

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

        Permission::query()->where('guard_name', 'admin')->whereIn('name', $this->permissions)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
