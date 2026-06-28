import { test, expect } from '@playwright/test';

/**
 * Regression coverage for the Command Center widget-width bug: an admin
 * with sidebar nav history (recent_nav/pinned_nav, written by
 * AdminNavService onto the same dashboard_preferences column
 * WidgetPreferenceService owns) was getting seeded via a naive auto-pack
 * instead of DashboardLayoutService::TAB_BLUEPRINT_LAYOUTS['command-center'],
 * producing wrong widget widths/pairing. Fixed in
 * WidgetPreferenceService::getAdminPreferences().
 *
 * Auth is handled by auth.setup.js (see playwright.config.js) — the shared
 * admin@oeparts.test session already has nav history from other suites in
 * this project, which is exactly the precondition that exposed the bug.
 */
test.describe('Command Center widget layout', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
        await page.waitForLoadState('networkidle');
    });

    const box = (page, gsId) => page.locator(`[gs-id="${gsId}"]`).boundingBox();

    test('order_stats_overview and parts_inquiry sit on the same row at 75/25 width', async ({ page }) => {
        const canvas = await page.locator('#dashboard-canvas').boundingBox();
        const stats = await box(page, 'order_stats_overview');
        const inquiry = await box(page, 'parts_inquiry');

        expect(stats).not.toBeNull();
        expect(inquiry).not.toBeNull();

        // Same row: vertical ranges overlap substantially.
        expect(Math.abs(stats.y - inquiry.y)).toBeLessThan(20);

        const statsRatio = stats.width / canvas.width;
        const inquiryRatio = inquiry.width / canvas.width;
        expect(statsRatio).toBeGreaterThan(0.65);
        expect(statsRatio).toBeLessThan(0.85);
        expect(inquiryRatio).toBeGreaterThan(0.15);
        expect(inquiryRatio).toBeLessThan(0.35);

        // parts_inquiry must be to the right of order_stats_overview, not stacked below.
        expect(inquiry.x).toBeGreaterThan(stats.x + stats.width - 20);
    });

    test('revenue_chart and order_volume_chart sit on the same row at 8/4 (2:1) width', async ({ page }) => {
        const revenue = await box(page, 'revenue_chart');
        const volume = await box(page, 'order_volume_chart');

        expect(revenue).not.toBeNull();
        expect(volume).not.toBeNull();
        expect(Math.abs(revenue.y - volume.y)).toBeLessThan(20);

        const ratio = revenue.width / volume.width;
        expect(ratio).toBeGreaterThan(1.7);
        expect(ratio).toBeLessThan(2.3);
    });

    test('order_status_distribution and latest_customers sit on the same row at 6/6 (1:1) width', async ({ page }) => {
        const distribution = await box(page, 'order_status_distribution');
        const customers = await box(page, 'latest_customers');

        expect(distribution).not.toBeNull();
        expect(customers).not.toBeNull();
        expect(Math.abs(distribution.y - customers.y)).toBeLessThan(20);

        const ratio = distribution.width / customers.width;
        expect(ratio).toBeGreaterThan(0.85);
        expect(ratio).toBeLessThan(1.15);
    });

    test('order_stats_overview stat values are not blank', async ({ page }) => {
        // Not scoped to [gs-id="order_stats_overview"] — Livewire re-parents
        // this widget's rendered content such that it's no longer a CSS
        // descendant of the grid-stack-item wrapper at runtime (confirmed by
        // direct inspection: 0 matches scoped vs 3 matches unscoped).
        // order_stats_overview is the only StatsOverviewWidget on this tab,
        // so the unscoped selector is unambiguous here regardless.
        const values = await page.locator('.fi-wi-stats-overview-stat-value').allTextContents();

        expect(values.length).toBeGreaterThanOrEqual(3);
        for (const value of values) {
            expect(value.trim().length).toBeGreaterThan(0);
        }
    });
});
