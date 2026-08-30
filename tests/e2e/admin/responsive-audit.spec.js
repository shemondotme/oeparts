import { test, expect } from '@playwright/test';

/**
 * Admin (Filament) panel responsive/visual-QA sweep — the storefront got
 * this treatment already (tests/e2e/guest/responsive-audit.spec.js); the
 * admin panel never has, despite carrying the same heavy custom
 * "Industrial Blueprint" theming (custom fonts, custom CSS variables,
 * custom topbar/sidebar chrome) layered on top of Filament's own UI,
 * which is exactly the kind of override that can silently break Filament's
 * built-in responsive behavior at phone/tablet widths.
 *
 * Pages below are a representative sample across the panel's different UI
 * shapes (dashboard widgets, a data table with filters, a multi-section
 * create form, a record detail/view page, a permission matrix grid, a
 * dashboard-style stats page) rather than every one of the ~40 resources —
 * Filament resources share the same underlying table/form/page components,
 * so a responsive bug in one of those components would show up across all
 * of them, not just the one page tested.
 *
 * Same two-pronged approach as the storefront audit: automated horizontal-
 * overflow assertions (cheap, catches real page-breaking bugs) plus
 * full-page screenshots at mobile/tablet for manual visual review (no
 * cheap DOM heuristic reliably flags "this looks wrong" without a wall of
 * false positives).
 */

const VIEWPORTS = [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'tablet', width: 768, height: 1024 },
];

const SCREENS_DIR = 'test-results/admin-responsive-audit';

const PAGES = [
    { label: 'dashboard', path: '/admin' },
    { label: 'products-list', path: '/admin/products' },
    { label: 'product-create', path: '/admin/products/create' },
    { label: 'orders-list', path: '/admin/orders' },
    { label: 'manufacturers-list', path: '/admin/manufacturers' },
    { label: 'carriers-list', path: '/admin/carriers' },
    { label: 'site-copy-library', path: '/admin/settings/site-copy-library' },
    { label: 'error-monitor', path: '/admin/system/error-monitor' },
    { label: 'permission-matrix', path: '/admin/system/permission-matrix' },
    { label: 'backup-dashboard', path: '/admin/system/backup-dashboard' },
];

test.describe('Admin panel responsive layout audit', () => {
    for (const vp of VIEWPORTS) {
        test.describe(`@ ${vp.name} (${vp.width}px)`, () => {
            for (const pg of PAGES) {
                test(`${pg.label}: no horizontal overflow`, async ({ page }) => {
                    await page.setViewportSize({ width: vp.width, height: vp.height });
                    await page.goto(pg.path, { waitUntil: 'domcontentloaded' });
                    await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });
                    await page.waitForTimeout(500);

                    const { scrollWidth, clientWidth } = await page.evaluate(() => ({
                        scrollWidth: document.documentElement.scrollWidth,
                        clientWidth: document.documentElement.clientWidth,
                    }));

                    await page.screenshot({ path: `${SCREENS_DIR}/${pg.label}--${vp.name}.png`, fullPage: true });

                    expect(scrollWidth, `${pg.label} @ ${vp.width}px: scrollWidth ${scrollWidth} vs viewport ${clientWidth}`)
                        .toBeLessThanOrEqual(clientWidth + 1);
                });
            }
        });
    }

    test('sidebar navigation is reachable at mobile width', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('nav.fi-topbar', { timeout: 30000 });
        await page.waitForTimeout(500);

        const sidebarToggle = page.locator('.fi-topbar button[aria-label*="sidebar" i], .fi-topbar button[aria-label*="menu" i]').first();
        await expect(sidebarToggle).toBeVisible();
        await sidebarToggle.click();
        await page.waitForTimeout(400);

        await page.screenshot({ path: `${SCREENS_DIR}/sidebar-open--mobile.png`, fullPage: false });

        const sidebarLink = page.locator('.fi-sidebar-nav a').first();
        await expect(sidebarLink).toBeVisible();
    });
});
