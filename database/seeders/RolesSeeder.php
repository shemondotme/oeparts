<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // All permissions, scoped to 'admin' guard
        $permissions = [
            // Orders
            'view orders',
            'edit orders',
            'cancel orders',
            'export orders',

            // Refunds
            'view refunds',
            'process refunds',

            // Products / Catalog
            'view products',
            'create products',
            'edit products',
            'delete products',
            'import products',
            'bulk update products',
            'view manufacturers',
            'create manufacturers',
            'edit manufacturers',
            'delete manufacturers',
            'view car models',
            'create car models',
            'edit car models',
            'delete car models',

            // Customers
            'view customers',
            'edit customers',
            'delete customers',
            'export customers',

            // Coupons
            'view coupons',
            'create coupons',
            'edit coupons',
            'delete coupons',

            // Inquiries
            'view inquiries',
            'edit inquiries',

            // Content / CMS
            'view sections',
            'edit sections',
            'view blog',
            'create blog',
            'edit blog',
            'delete blog',
            'view pages',
            'create pages',
            'edit pages',
            'delete pages',
            'view media',
            'upload media',
            'delete media',
            'view menus',
            'edit menus',
            'view redirects',
            'edit redirects',
            'view testimonials',
            'edit testimonials',
            'view faqs',
            'edit faqs',
            'view newsletter',
            'export newsletter',
            'view contact messages',
            'edit contact messages',

            // Reports
            'view reports',
            'export reports',

            // Settings
            'view settings',
            'edit settings',

            // System
            'view activity logs',
            'view login logs',
            'view cron logs',
            'view failed jobs',
            'retry failed jobs',
            'view ip blocklist',
            'edit ip blocklist',
            'view translations',
            'edit translations',
            'view health',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission, 'guard_name' => 'admin'],
                ['name' => $permission, 'guard_name' => 'admin']
            );
        }

        // ── SUPER ADMIN ───────────────────────────────────────────────────────
        // Gets all permissions via Gate::before() hook — no explicit assignment needed.
        $superAdmin = Role::updateOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'admin'],
            ['name' => 'super_admin', 'guard_name' => 'admin']
        );

        // ── MANAGER ──────────────────────────────────────────────────────────
        $manager = Role::updateOrCreate(
            ['name' => 'manager', 'guard_name' => 'admin'],
            ['name' => 'manager', 'guard_name' => 'admin']
        );
        $manager->syncPermissions([
            'view orders', 'edit orders', 'cancel orders', 'export orders',
            'view refunds', 'process refunds',
            'view products', 'create products', 'edit products', 'delete products',
            'import products', 'bulk update products',
            'view manufacturers', 'create manufacturers', 'edit manufacturers',
            'view car models', 'create car models', 'edit car models',
            'view customers', 'edit customers', 'export customers',
            'view coupons', 'create coupons', 'edit coupons',
            'view inquiries', 'edit inquiries',
            'view reports', 'export reports',
            'view activity logs',
            'view health',
        ]);

        // ── CATALOG ADMIN ────────────────────────────────────────────────────
        $catalogAdmin = Role::updateOrCreate(
            ['name' => 'catalog_admin', 'guard_name' => 'admin'],
            ['name' => 'catalog_admin', 'guard_name' => 'admin']
        );
        $catalogAdmin->syncPermissions([
            'view products', 'create products', 'edit products', 'delete products',
            'import products', 'bulk update products',
            'view manufacturers', 'create manufacturers', 'edit manufacturers', 'delete manufacturers',
            'view car models', 'create car models', 'edit car models', 'delete car models',
            'view media', 'upload media',
        ]);

        // ── SUPPORT ──────────────────────────────────────────────────────────
        $support = Role::updateOrCreate(
            ['name' => 'support', 'guard_name' => 'admin'],
            ['name' => 'support', 'guard_name' => 'admin']
        );
        $support->syncPermissions([
            'view orders', 'edit orders',
            'view refunds',
            'view customers', 'edit customers',
            'view inquiries', 'edit inquiries',
            'view contact messages', 'edit contact messages',
            'view products',
        ]);
    }
}
