<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Content-management permissions (pages, blog, sections, FAQs, menus, media,
 * testimonials) existed but were never assigned to any role, so only
 * super_admin (which bypasses the gate) could reach the Content cluster —
 * every other role hit a 403 despite the cluster granting them access.
 *
 * Grant the content permissions to the roles the Content cluster is meant for
 * (admin, catalog_admin). guard_name is 'admin' — Spatie is on the Admin model.
 */
return new class extends Migration
{
    private array $contentPermissions = [
        'view pages', 'create pages', 'edit pages', 'delete pages',
        'view blog', 'create blog', 'edit blog', 'delete blog',
        'view faqs', 'create faqs', 'edit faqs', 'delete faqs',
        'view sections', 'edit sections',
        'view menus', 'edit menus',
        'view media', 'upload media', 'delete media',
        'view testimonials', 'create testimonials', 'edit testimonials', 'delete testimonials',
    ];

    private array $roles = ['admin', 'catalog_admin'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existing = Permission::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', $this->contentPermissions)
            ->pluck('name')
            ->all();

        foreach ($this->roles as $roleName) {
            $role = Role::query()->where('guard_name', 'admin')->where('name', $roleName)->first();

            if ($role) {
                $role->givePermissionTo($existing);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->roles as $roleName) {
            $role = Role::query()->where('guard_name', 'admin')->where('name', $roleName)->first();

            if ($role) {
                $role->revokePermissionTo($this->contentPermissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
