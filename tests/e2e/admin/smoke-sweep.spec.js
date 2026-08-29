import { test, expect } from '@playwright/test';

/**
 * Comprehensive admin-panel smoke sweep: visits every Filament resource's
 * List/Create/View/Edit page, every settings screen, every system/report
 * page, and the dashboard — asserting none of them 500, throw an uncaught
 * JS exception, or fail to render the real Filament shell (as opposed to
 * a blank page or a framework error page).
 *
 * This is a BREADTH check (catches "page X is broken" across the whole
 * ~150-page admin surface fast), not a depth check — CRUD correctness,
 * validation, and custom actions are covered by the dedicated per-area
 * spec files alongside this one. Runs in the `chromium` project (already
 * authenticated as super_admin via auth.setup.js's storageState).
 *
 * Record ids below are real rows queried live from this dev DB
 * (`php artisan tinker`) — a resource with no existing rows has its
 * View/Edit entries omitted (`id: null`) since there is nothing to load;
 * its List/Create pages are still exercised.
 */

const RESOURCES = [
    { name: 'AbandonedCart', slug: 'abandoned-carts', id: null, create: false, view: true, edit: false },
    { name: 'ActivityLog', slug: 'activity-logs', id: 614, create: false, view: true, edit: false },
    { name: 'Admin', slug: 'admins', id: 5, create: true, view: true, edit: true },
    { name: 'BlogPost', slug: 'content/blog-posts', id: 10, create: true, view: true, edit: true },
    { name: 'CarModel', slug: 'car-models', id: 3, create: true, view: true, edit: true },
    { name: 'Carrier', slug: 'carriers', id: 8, create: true, view: true, edit: true },
    { name: 'Category', slug: 'content/categories', id: 6, create: true, view: true, edit: true },
    { name: 'Condition', slug: 'conditions', id: 2, create: true, view: true, edit: true },
    { name: 'ContactMessage', slug: 'contact-messages', id: 14, create: false, view: true, edit: false },
    { name: 'Coupon', slug: 'coupons', id: 5, create: true, view: true, edit: true },
    { name: 'CronLog', slug: 'cron-logs', id: null, create: false, view: true, edit: false },
    { name: 'Customer', slug: 'customers', id: 60, create: true, view: true, edit: true },
    { name: 'EmailLog', slug: 'email-logs', id: 82, create: false, view: true, edit: false },
    { name: 'FailedSearchLog', slug: 'failed-search-logs', id: 28, create: false, view: true, edit: false },
    { name: 'Faq', slug: 'content/faqs', id: 5, create: true, view: true, edit: true },
    { name: 'IpBlocklist', slug: 'ip-blocklists', id: null, create: true, view: true, edit: true },
    { name: 'Language', slug: 'languages', id: 5, create: true, view: true, edit: true },
    { name: 'LoginLog', slug: 'login-logs', id: 40, create: false, view: true, edit: false },
    { name: 'Manufacturer', slug: 'manufacturers', id: 19, create: true, view: true, edit: true },
    { name: 'MediaFile', slug: 'content/media-files', id: null, create: false, view: false, edit: true },
    { name: 'Menu', slug: 'content/menus', id: null, create: true, view: true, edit: true },
    { name: 'NewsletterCampaign', slug: 'newsletter-campaigns', id: null, create: true, view: true, edit: true },
    { name: 'NewsletterSubscriber', slug: 'newsletter-subscribers', id: 4, create: true, view: true, edit: true },
    { name: 'NotFoundLog', slug: 'not-found-logs', id: 14, create: false, view: true, edit: false },
    { name: 'Order', slug: 'orders', id: 82, create: true, view: true, edit: true },
    { name: 'Page', slug: 'content/pages', id: 6, create: true, view: true, edit: true },
    { name: 'PartInquiry', slug: 'part-inquiries', id: 14, create: true, view: true, edit: false },
    { name: 'Payment', slug: 'payments', id: 5, create: false, view: true, edit: false },
    { name: 'Product', slug: 'products', id: 106, create: true, view: true, edit: true },
    { name: 'Redirect', slug: 'redirects', id: 36, create: true, view: true, edit: true },
    { name: 'RefundRequest', slug: 'refund-requests', id: 15, create: false, view: true, edit: true },
    { name: 'Review', slug: 'content/reviews', id: 4, create: false, view: true, edit: true },
    { name: 'Role', slug: 'roles', id: 5, create: true, view: true, edit: true },
    { name: 'SearchLog', slug: 'search-logs', id: 153, create: false, view: true, edit: false },
    { name: 'Section', slug: 'content/sections', id: 14, create: true, view: true, edit: true },
    { name: 'SeoMeta', slug: 'seo-metas', id: 1, create: false, view: false, edit: true },
    { name: 'ShippingMethod', slug: 'shipping-methods', id: 3, create: true, view: true, edit: true },
    { name: 'ShippingZone', slug: 'shipping-zones', id: 1, create: true, view: true, edit: true },
    { name: 'TaxRate', slug: 'tax-rates', id: null, create: true, view: true, edit: true },
    { name: 'Testimonial', slug: 'content/testimonials', id: 6, create: true, view: true, edit: true },
    { name: 'Translation', slug: 'translations', id: null, create: true, view: true, edit: true },
];

const CUSTOM_PAGES = [
    { name: 'Dashboard', url: '/admin' },
    { name: 'Widget preferences', url: '/admin/preferences/widgets' },
    { name: 'Product import', url: '/admin/product-import' },
    { name: 'Bulk update products', url: '/admin/bulk-update-products' },
    { name: 'Bulk update log', url: '/admin/bulk-update-log-page' },
    { name: 'Inventory log', url: '/admin/inventory-log-page' },
    { name: 'Content revisions', url: '/admin/content/content-revision-page' },
];

const REPORTS = [
    { name: 'Reports hub', url: '/admin/reports' },
    { name: 'Checkout drop-off report', url: '/admin/reports/checkout-dropoff-report' },
    { name: 'Customers report', url: '/admin/reports/customers-report' },
    { name: 'Sales report', url: '/admin/reports/sales-report' },
    { name: 'Search intelligence report', url: '/admin/reports/search-intelligence-report' },
];

const SYSTEM_PAGES = [
    { name: 'Backup dashboard', url: '/admin/system/backup-dashboard' },
    { name: 'Cache dashboard', url: '/admin/system/cache-dashboard' },
    { name: 'Error monitor', url: '/admin/system/error-monitor' },
    { name: 'Failed jobs', url: '/admin/system/failed-jobs-page' },
    { name: 'Health check dashboard', url: '/admin/system/health-check-dashboard' },
    { name: 'Help page', url: '/admin/system/help-page' },
    { name: 'Log viewer', url: '/admin/system/log-viewer-page' },
    { name: 'Permission matrix', url: '/admin/system/permission-matrix' },
    { name: 'Queue monitor', url: '/admin/system/queue-monitor' },
    { name: 'Scheduled tasks', url: '/admin/system/scheduled-tasks-page' },
    { name: 'Server monitor', url: '/admin/system/server-monitor' },
    { name: 'Setup assistant', url: '/admin/system/setup-assistant' },
    { name: 'System updates', url: '/admin/system/system-updates' },
    { name: 'Update history', url: '/admin/system/update-history-page' },
];

const SETTINGS_PAGES = [
    { name: 'Settings hub', url: '/admin/settings' },
    { name: 'Appearance settings', url: '/admin/settings/appearance-settings' },
    { name: 'Customization settings', url: '/admin/settings/customization-settings' },
    { name: 'General & brand settings', url: '/admin/settings/general-brand-settings' },
    { name: 'Localization settings', url: '/admin/settings/localization-settings' },
    { name: 'Marketing settings', url: '/admin/settings/marketing-settings' },
    { name: 'Performance settings', url: '/admin/settings/performance-settings' },
    { name: 'Search & catalog settings', url: '/admin/settings/search-catalog-settings' },
    { name: 'Security & access settings', url: '/admin/settings/security-access-settings' },
    { name: 'SEO Control Center', url: '/admin/settings/seo-settings' },
    { name: 'Site Copy Library', url: '/admin/settings/site-copy-library' },
    { name: 'Store operations settings', url: '/admin/settings/store-operations-settings' },
    { name: 'System maintenance settings', url: '/admin/settings/system-maintenance-settings' },
    { name: 'SEO Health Dashboard', url: '/admin/seo-settings/health' },
];

/**
 * Visits a page and asserts it rendered as a real, successful Filament
 * page: no 5xx, the persistent topbar mounted (proof the panel shell
 * actually booted, not a blank/error page), and no uncaught JS exception
 * fired during load.
 */
async function assertPageIsHealthy(page, url) {
    const pageErrors = [];
    const onError = (err) => pageErrors.push(err.message);
    page.on('pageerror', onError);

    try {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

        expect(response, `no response for ${url}`).not.toBeNull();
        expect(response.status(), `HTTP ${response.status()} on ${url}`).toBeLessThan(500);

        await expect(page.locator('nav.fi-topbar'), `Filament shell never rendered on ${url}`).toBeVisible({ timeout: 15000 });

        await page.waitForTimeout(300);
        expect(pageErrors, `uncaught JS error(s) on ${url}: ${pageErrors.join('; ')}`).toEqual([]);
    } finally {
        page.off('pageerror', onError);
    }
}

test.describe('Admin smoke sweep: resource pages', () => {
    for (const r of RESOURCES) {
        test(`${r.name}: list page loads clean`, async ({ page }) => {
            await assertPageIsHealthy(page, `/admin/${r.slug}`);
        });

        if (r.create) {
            test(`${r.name}: create page loads clean`, async ({ page }) => {
                await assertPageIsHealthy(page, `/admin/${r.slug}/create`);
            });
        }

        if (r.view && r.id !== null) {
            test(`${r.name}: view page loads clean`, async ({ page }) => {
                await assertPageIsHealthy(page, `/admin/${r.slug}/${r.id}`);
            });
        }

        if (r.edit && r.id !== null) {
            test(`${r.name}: edit page loads clean`, async ({ page }) => {
                await assertPageIsHealthy(page, `/admin/${r.slug}/${r.id}/edit`);
            });
        }
    }
});

test.describe('Admin smoke sweep: custom pages', () => {
    for (const p of CUSTOM_PAGES) {
        test(`${p.name} loads clean`, async ({ page }) => {
            await assertPageIsHealthy(page, p.url);
        });
    }
});

test.describe('Admin smoke sweep: reports', () => {
    for (const p of REPORTS) {
        test(`${p.name} loads clean`, async ({ page }) => {
            await assertPageIsHealthy(page, p.url);
        });
    }
});

test.describe('Admin smoke sweep: system pages', () => {
    for (const p of SYSTEM_PAGES) {
        test(`${p.name} loads clean`, async ({ page }) => {
            await assertPageIsHealthy(page, p.url);
        });
    }
});

test.describe('Admin smoke sweep: settings pages', () => {
    for (const p of SETTINGS_PAGES) {
        test(`${p.name} loads clean`, async ({ page }) => {
            await assertPageIsHealthy(page, p.url);
        });
    }
});
