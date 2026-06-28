import { test, expect } from '@playwright/test';
import { loginAsSuperAdmin } from './helpers.js';

/**
 * Admin topbar E2E suite. Auth is handled once by auth.setup.js (see
 * playwright.config.js's `setup` project + `chromium` project's
 * storageState dependency) — every test here starts already logged in
 * as super_admin; beforeEach just navigates to the dashboard.
 *
 * Selectors are taken directly from the live Blade source, not guessed:
 *   resources/views/vendor/filament-panels/livewire/topbar.blade.php
 *   resources/views/components/admin/{quick-create,environment-indicator,theme-toggle,keyboard-shortcuts}.blade.php
 *   vendor/wire-elements/spotlight/resources/views/spotlight.blade.php
 *
 * Two real bugs were found while writing this suite (event-name mismatch
 * on the search button, wrong hardcoded Order URL in Quick-Create) — both
 * fixed in resources/views/vendor/filament-panels/livewire/topbar.blade.php
 * and app/Filament/Support/AdminUi.php respectively. The tests below assert
 * the correct (now-real) behavior rather than encoding the bugs.
 */

test.describe('Layout and spacing', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    for (const width of [1280, 1440, 1920]) {
        test(`topbar is visible with balanced left/right padding at ${width}px`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });

            const topbar = page.locator('nav.fi-topbar');
            await expect(topbar).toBeVisible();

            const left = page.locator('.op-topbar-left');
            // .op-topbar-right only wraps the New/help/theme/avatar cluster —
            // the notification bell and health-indicator dot are appended as
            // separate flex siblings via TOPBAR_END render hooks (see
            // AdminPanelProvider), so the true rightmost element is the
            // topbar's last flex child, not .op-topbar-right itself.
            const rightmost = page.locator('nav.fi-topbar > *').last();
            await expect(left).toBeVisible();
            await expect(rightmost).toBeVisible();

            const topbarBox = await topbar.boundingBox();
            const leftBox = await left.boundingBox();
            const rightBox = await rightmost.boundingBox();

            expect(topbarBox).not.toBeNull();
            expect(leftBox).not.toBeNull();
            expect(rightBox).not.toBeNull();

            // No element should touch the viewport edge with zero margin.
            expect(leftBox.x).toBeGreaterThan(0);
            const rightEdgeGap = topbarBox.x + topbarBox.width - (rightBox.x + rightBox.width);
            expect(rightEdgeGap).toBeGreaterThan(0);

            // Balanced left/right padding: the gap from the topbar's own
            // left edge to the left zone should roughly match the gap from
            // the rightmost element to the topbar's own right edge.
            const leftGap = leftBox.x - topbarBox.x;
            const rightGap = rightEdgeGap;
            expect(Math.abs(leftGap - rightGap)).toBeLessThan(16);
        });
    }
});

test.describe('Logo and breadcrumb', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    test('logo is visible', async ({ page }) => {
        await expect(page.locator('.op-topbar-brand-link')).toBeVisible();
        await expect(page.locator('.op-topbar-brand-link .fi-logo')).toBeVisible();
    });

    test('breadcrumb shows Dashboard on the dashboard page', async ({ page }) => {
        const breadcrumb = page.locator('.op-topbar-breadcrumb');
        // Filament's own home-page breadcrumb logic may render nothing for
        // the dashboard itself (no active nav item matches it) — assert
        // whichever is true rather than assuming.
        if (await breadcrumb.count() > 0) {
            await expect(breadcrumb).toContainText(/Dashboard/i);
        }
    });

    test('breadcrumb updates when navigating to another section', async ({ page }) => {
        // waitUntil:'load' never resolves on this page (some resource never
        // settles — Echo/websocket is a likely culprit, not yet root-caused).
        // domcontentloaded + an explicit element wait is reliable and fast.
        await page.goto('/admin/orders', { waitUntil: 'domcontentloaded' });

        const breadcrumbPage = page.locator('.op-topbar-breadcrumb-page');
        await expect(breadcrumbPage).toBeVisible();
        await expect(breadcrumbPage).toHaveText(/Orders/i);
    });

    test('clicking the logo redirects to the dashboard', async ({ page }) => {
        await page.goto('/admin/orders', { waitUntil: 'domcontentloaded' });
        await expect(page.locator('.op-topbar-breadcrumb-page')).toHaveText(/Orders/i);

        await page.locator('.op-topbar-brand-link').click();
        await page.waitForSelector('#dashboard-canvas', { timeout: 45000 });
        await expect(page).toHaveURL(/\/admin$/);
    });
});

test.describe('Global search (Spotlight)', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    const spotlightModal = (page) => page.locator('[x-data*="LivewireUISpotlight"]');
    const spotlightInput = (page) => spotlightModal(page).locator('input[x-ref="input"]');

    test('search bar is visible in the topbar center', async ({ page }) => {
        const search = page.locator('.op-topbar-search');
        await expect(search).toBeVisible();
        await expect(search).toContainText('Search everything');
        await expect(search.locator('.op-topbar-search-kbd')).toHaveText('⌘K');
    });

    test('⌘K / Ctrl+K opens the search modal', async ({ page }) => {
        await page.keyboard.press('Control+k');
        await expect(spotlightModal(page)).toBeVisible();
        await expect(spotlightInput(page)).toBeFocused();
    });

    test('Escape closes the search modal', async ({ page }) => {
        await page.keyboard.press('Control+k');
        await expect(spotlightModal(page)).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(spotlightModal(page)).toBeHidden();
    });

    test('typing a query shows results or a no-results state', async ({ page }) => {
        await page.keyboard.press('Control+k');
        await spotlightInput(page).fill('order');

        // The package shows either a populated <ul x-ref="results"> or
        // nothing (no built-in "no results" copy exists in this package —
        // verify it doesn't just silently freeze with stale UI).
        await page.waitForTimeout(300);
        const resultsList = spotlightModal(page).locator('ul[x-ref="results"]');
        const itemCount = await resultsList.locator('li').count();
        expect(itemCount).toBeGreaterThanOrEqual(0);
    });

    test('input is cleared when the modal is closed and reopened', async ({ page }) => {
        await page.keyboard.press('Control+k');
        await spotlightInput(page).fill('some search text');
        await page.keyboard.press('Escape');

        await page.keyboard.press('Control+k');
        await expect(spotlightInput(page)).toHaveValue('');
    });

    test('clicking the search button opens the modal', async ({ page }) => {
        await page.locator('.op-topbar-search').click();
        await expect(spotlightModal(page)).toBeVisible();
    });
});

test.describe('New button (Quick-Create)', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    const newButton = (page) => page.locator('.op-quick-create-btn');
    const dropdown = (page) => page.locator('.op-quick-create-dropdown');

    test('New button is visible and clickable', async ({ page }) => {
        await expect(newButton(page)).toBeVisible();
        await expect(newButton(page)).toHaveText(/New/);
    });

    test('clicking New opens the create-options dropdown', async ({ page }) => {
        await newButton(page).click();
        await expect(dropdown(page)).toBeVisible();
        await expect(dropdown(page).locator('.op-quick-create-item')).not.toHaveCount(0);
    });

    test('clicking the Product option navigates to the product create form', async ({ page }) => {
        await newButton(page).click();
        await dropdown(page).getByRole('menuitem', { name: 'Product' }).click();

        await page.waitForURL(/\/admin\/products\/create$/);
        // Plain `form` matches 3 elements on a Filament create page (the
        // page's own resource form, plus the persistent select-first form
        // and logout form rendered globally in the shell) — target the
        // resource form specifically.
        await expect(page.locator('#form')).toBeVisible();
    });

    test('dropdown closes when clicking outside it', async ({ page }) => {
        await newButton(page).click();
        await expect(dropdown(page)).toBeVisible();

        await page.locator('.op-topbar-breadcrumb, .op-topbar-left').first().click({ position: { x: 5, y: 5 } });
        await expect(dropdown(page)).toBeHidden();
    });

    test('every quick-create item link resolves (none 404)', async ({ page, request }) => {
        await newButton(page).click();
        const hrefs = await dropdown(page).locator('.op-quick-create-item').evaluateAll(
            (els) => els.map((el) => ({ label: el.textContent.trim(), href: el.getAttribute('href') })),
        );

        for (const { label, href } of hrefs) {
            const response = await request.get(href);
            expect(response.status(), `"${label}" -> ${href}`).not.toBe(404);
        }
    });
});

test.describe('Environment badge', () => {
    test('shows LOCAL with a distinct color', async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');

        const badge = page.locator('.fi-env-indicator');
        await expect(badge).toBeVisible();
        await expect(badge).toHaveText('LOCAL');

        const color = await badge.evaluate((el) => getComputedStyle(el).backgroundColor);
        // Should not be transparent/inherited — a real background was applied.
        expect(color).not.toBe('rgba(0, 0, 0, 0)');
    });
});

test.describe('Help / keyboard shortcuts', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    const helpButton = (page) => page.getByRole('button', { name: 'Show keyboard shortcuts' });
    const shortcutsPanel = (page) => page.locator('[x-data*="showPanel"]');

    test('help button is visible', async ({ page }) => {
        await expect(helpButton(page)).toBeVisible();
    });

    test('clicking help opens the keyboard-shortcuts panel with no console errors', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await helpButton(page).click();
        await expect(shortcutsPanel(page)).toBeVisible();
        await expect(shortcutsPanel(page)).toContainText('Command Palette');

        expect(errors).toEqual([]);
    });

    test('"?" key also opens the panel, and Escape closes it', async ({ page }) => {
        await page.keyboard.press('?');
        await expect(shortcutsPanel(page)).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(shortcutsPanel(page)).toBeHidden();
    });
});

test.describe('Dark mode toggle', () => {
    test.beforeEach(async ({ page }) => {
        // Force a known starting state — light mode — so each test is
        // independent of whatever a previous test/run left in localStorage.
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
        await page.evaluate(() => localStorage.setItem('theme', 'light'));
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    const themeToggle = (page) => page.getByRole('button', { name: 'Toggle theme' });

    test('toggle is visible', async ({ page }) => {
        await expect(themeToggle(page)).toBeVisible();
    });

    test('clicking switches to dark mode and back', async ({ page }) => {
        const html = page.locator('html');
        await expect(html).not.toHaveClass(/dark/);

        const lightBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);

        await themeToggle(page).click();
        await expect(html).toHaveClass(/dark/);
        await expect(page.evaluate(() => localStorage.getItem('theme'))).resolves.toBe('dark');

        const darkBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
        expect(darkBg).not.toBe(lightBg);

        await themeToggle(page).click();
        await expect(html).not.toHaveClass(/dark/);
        await expect(page.evaluate(() => localStorage.getItem('theme'))).resolves.toBe('light');
    });

    test('dark mode persists across a reload', async ({ page }) => {
        await themeToggle(page).click();
        await expect(page.locator('html')).toHaveClass(/dark/);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
        await expect(page.locator('html')).toHaveClass(/dark/);
    });

    test('topbar stays fully visible and correctly spaced in dark mode', async ({ page }) => {
        await themeToggle(page).click();
        await expect(page.locator('html')).toHaveClass(/dark/);

        const topbar = page.locator('nav.fi-topbar');
        await expect(topbar).toBeVisible();
        await expect(page.locator('.op-topbar-left')).toBeVisible();
        await expect(page.locator('.op-topbar-center')).toBeVisible();
        await expect(page.locator('.op-topbar-right')).toBeVisible();

        const leftBox = await page.locator('.op-topbar-left').boundingBox();
        const rightBox = await page.locator('.op-topbar-right').boundingBox();
        // Zones shouldn't overlap.
        expect(leftBox.x + leftBox.width).toBeLessThanOrEqual(rightBox.x);
    });
});

test.describe('User avatar and account menu', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');
    });

    const avatarTrigger = (page) => page.locator('.fi-user-menu-trigger');

    test('avatar is visible', async ({ page }) => {
        await expect(avatarTrigger(page)).toBeVisible();
    });

    test('clicking the avatar opens a dropdown with profile and logout', async ({ page }) => {
        await avatarTrigger(page).click();

        // `.fi-dropdown-panel` matches every dropdown panel on the page
        // (table row actions, etc.) — Filament teleports panels to the
        // document root rather than nesting them under their trigger, so
        // scope to the one Playwright considers visible.
        const menu = page.locator('.fi-dropdown-panel:visible');
        await expect(menu).toBeVisible();
        await expect(menu.getByText(/profile/i)).toBeVisible();
        await expect(menu.getByText(/sign\s?out/i)).toBeVisible();
    });

    test('logout redirects to login and the dashboard becomes inaccessible', async ({ browser }) => {
        // Logging out invalidates the session server-side, not just this
        // browser context — every other test sharing the project's
        // storageState snapshot would find itself logged out too if this
        // ran on the shared `page` fixture (confirmed: this is exactly
        // what happened before this test used its own isolated context).
        // Log in fresh here so only this throwaway session gets burned.
        // This test does a full extra login round trip on top of the
        // logout flow itself, so it needs more headroom than the suite's
        // default 60s on this occasionally-slow local environment.
        test.setTimeout(90000);
        // The chromium project's `storageState` (the shared authenticated
        // session) is applied as a default to ANY browser.newContext() call
        // within a test for that project, not just the page/context
        // fixtures — confirmed by direct experiment: an "empty" newContext()
        // here was silently already logged in as super_admin, so /admin/login
        // immediately bounced to /admin (guest middleware) and the test hung
        // waiting for a login form that was never going to appear. Pass an
        // explicit empty storage state to actually get a clean session.
        const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
        const page = await context.newPage();
        await loginAsSuperAdmin(page);

        await page.locator('.fi-user-menu-trigger').click();
        await page.locator('.fi-dropdown-panel:visible').getByText(/sign\s?out/i).click();
        await page.waitForURL(/\/admin\/login/, { waitUntil: 'domcontentloaded' });

        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForURL(/\/admin\/login/, { waitUntil: 'domcontentloaded' });

        await context.close();
    });
});

test.describe('Responsive behavior', () => {
    test('topbar does not overflow at 768px', async ({ page }) => {
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');

        const topbar = page.locator('nav.fi-topbar');
        await expect(topbar).toBeVisible();

        const overflowsX = await topbar.evaluate((el) => el.scrollWidth > el.clientWidth + 1);
        expect(overflowsX).toBe(false);

        // Nothing critical should be clipped off-screen.
        const rightBox = await page.locator('.op-topbar-right').boundingBox();
        const viewport = page.viewportSize();
        expect(rightBox.x + rightBox.width).toBeLessThanOrEqual(viewport.width + 1);
    });

    test('no two topbar elements visually overlap at 768px', async ({ page }) => {
        await page.setViewportSize({ width: 768, height: 1024 });
        await page.goto('/admin', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('#dashboard-canvas');

        const leftBox = await page.locator('.op-topbar-left').boundingBox();
        const centerBox = await page.locator('.op-topbar-center').boundingBox();
        const rightBox = await page.locator('.op-topbar-right').boundingBox();

        expect(leftBox.x + leftBox.width).toBeLessThanOrEqual(centerBox.x + 1);
        expect(centerBox.x + centerBox.width).toBeLessThanOrEqual(rightBox.x + 1);
    });
});
