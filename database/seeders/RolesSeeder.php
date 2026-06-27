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

            // Payments
            'view payments',
            'edit payments',
            'delete payments',

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
            'view conditions',
            'create conditions',
            'edit conditions',
            'delete conditions',
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',

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
            'create testimonials',
            'edit testimonials',
            'delete testimonials',
            'view faqs',
            'create faqs',
            'edit faqs',
            'delete faqs',

            // Marketing
            'view newsletters',
            'create newsletters',
            'edit newsletters',
            'delete newsletters',
            'export newsletter',
            'view abandoned carts',
            'view contact messages',
            'edit contact messages',
            'view email logs',

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
            'view search logs',
            'view failed search logs',
            'view failed jobs',
            'retry failed jobs',
            'view ip blocklist',
            'create ip blocklist',
            'edit ip blocklist',
            'delete ip blocklist',
            'view translations',
            'edit translations',
            'view health',
            'view languages',
            'create languages',
            'edit languages',
            'delete languages',
            'view seo meta',
            'create seo meta',
            'edit seo meta',
            'delete seo meta',
            'view admins',
            'create admins',
            'edit admins',
            'delete admins',
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'view shipping methods',
            'create shipping methods',
            'edit shipping methods',
            'delete shipping methods',
            'view shipping zones',
            'create shipping zones',
            'edit shipping zones',
            'delete shipping zones',
            'view carriers',
            'create carriers',
            'edit carriers',
            'delete carriers',
            'view system information',
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
            'view payments',
            'view products', 'create products', 'edit products', 'delete products',
            'import products', 'bulk update products',
            'view manufacturers', 'create manufacturers', 'edit manufacturers',
            'view car models', 'create car models', 'edit car models',
            'view conditions', 'create conditions', 'edit conditions',
            'view categories', 'create categories', 'edit categories',
            'view customers', 'edit customers', 'export customers',
            'view coupons', 'create coupons', 'edit coupons', 'delete coupons',
            'view inquiries', 'edit inquiries',
            'view contact messages', 'edit contact messages',
            'view abandoned carts',
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
            'view conditions', 'create conditions', 'edit conditions', 'delete conditions',
            'view categories', 'create categories', 'edit categories', 'delete categories',
            'view media', 'upload media',
        ]);

        // ── ADMIN ─────────────────────────────────────────────────────────────
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin', 'guard_name' => 'admin'],
            ['name' => 'admin', 'guard_name' => 'admin']
        );
        $adminRole->syncPermissions([
            // Everything manager has
            'view orders', 'edit orders', 'cancel orders', 'export orders',
            'view refunds', 'process refunds',
            'view payments',
            'view products', 'create products', 'edit products', 'delete products',
            'import products', 'bulk update products',
            'view manufacturers', 'create manufacturers', 'edit manufacturers',
            'view car models', 'create car models', 'edit car models',
            'view conditions', 'create conditions', 'edit conditions',
            'view categories', 'create categories', 'edit categories',
            'view customers', 'edit customers', 'export customers',
            'view coupons', 'create coupons', 'edit coupons', 'delete coupons',
            'view inquiries', 'edit inquiries',
            'view contact messages', 'edit contact messages',
            'view abandoned carts',
            'view reports', 'export reports',
            'view activity logs',
            'view health',
            // Plus settings
            'view settings',
            'view system information',
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
